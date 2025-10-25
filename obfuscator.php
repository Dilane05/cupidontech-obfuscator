<?php
/**
 * cupidontech/obfuscator - obfuscator principal
 * Usage: php obfuscator.php [config_file]
 */

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/utils.php';

use PhpParser\{Node, NodeTraverser, NodeVisitorAbstract, ParserFactory, PrettyPrinter\Standard};

$configFile = $argv[1] ?? __DIR__ . '/obfuscator.config.php';
if (!file_exists($configFile)) {
    echo "Config introuvable: {$configFile}\n";
    exit(1);
}
$config = require $configFile;

echo "🔒 cupidontech/obfuscator — démarrage\n";

$parser = (new ParserFactory())->createForNewestSupportedVersion();
$printer = new Standard();
$names = [];

function uname_obf($o, &$m) {
    if (isset($m[$o])) return $m[$o];
    $c = ['O','o','О','о','Ο','ο','I','l','Ӏ','І','Ι','ι','a','а','ɑ','e','е','ԑ','c','с','ϲ','p','р','ρ','B','В','Β','H','Н','Η','K','К','Κ','M','М','Μ','T','Т','Τ','X','Х','Χ','Y','У','Υ','Z','Ζ'];
    $n = strtolower($c[array_rand($c)]);
    for($i=0; $i<rand(3,5); $i++) $n .= $c[array_rand($c)];
    $m[$o] = $n;
    return $n;
}

function xorEnc($d, $k) {
    $r='';
    for($i=0; $i<strlen($d); $i++)
        $r .= chr(ord($d[$i]) ^ ord($k[$i % strlen($k)]));
    return base64_encode($r);
}

function strip_comments($code) {
    $t = token_get_all($code);
    $o = '';
    foreach ($t as $i) {
        if (is_array($i)) {
            if ($i[0] !== T_COMMENT && $i[0] !== T_DOC_COMMENT) $o .= $i[1];
        } else $o .= $i;
    }
    return $o;
}

class CompactFinder extends NodeVisitorAbstract {
    private $vars = [];
    public function enterNode(Node $n) {
        if ($n instanceof Node\Expr\FuncCall && $n->name instanceof Node\Name && $n->name->toString() === 'compact') {
            foreach ($n->args as $arg) {
                if ($arg->value instanceof Node\Scalar\String_) $this->vars[] = $arg->value->value;
            }
        }
        return null;
    }
    public function getVars() { return array_unique($this->vars); }
}

class FinalVisitor extends NodeVisitorAbstract {
    private $m, $lw=false, $compactVars=[];
    private $k=['middleware','middlewareGroups','middlewareAliases','fillable','guarded','hidden','casts','table','primaryKey','timestamps','listeners','queryString','paginationTheme'];
    function __construct(&$map){$this->m=&$map;}
    public function beforeTraverse(array $nodes) {
        $finder = new CompactFinder();
        $trav = new NodeTraverser();
        $trav->addVisitor($finder);
        $trav->traverse($nodes);
        $this->compactVars = $finder->getVars();
        return null;
    }
    function enterNode(Node $n) {
        if ($n instanceof Node\Stmt\Class_ && $n->extends && $n->extends->toString() === 'Component') $this->lw=true;

        if ($n instanceof Node\Expr\FuncCall && $n->name instanceof Node\Name && $n->name->toString() === 'compact') {
            $items = [];
            foreach ($n->args as $arg) {
                if ($arg->value instanceof Node\Scalar\String_) {
                    $varName = $arg->value->value;
                    $items[] = new Node\Expr\ArrayItem(new Node\Expr\Variable($varName), new Node\Scalar\String_($varName));
                }
            }
            return new Node\Expr\Array_($items, ['kind' => Node\Expr\Array_::KIND_SHORT]);
        }

        if ($n instanceof Node\Expr\Variable && is_string($n->name)) {
            if (in_array($n->name, $this->compactVars)) return $n;
            if (!in_array($n->name, ['this','request','user','auth','session','_GET','_POST','_SERVER','_ENV']))
                $n->name = uname_obf($n->name, $this->m);
        }

        if ($n instanceof Node\Stmt\ClassMethod) {
            $fw = ['boot','register','handle','mount','hydrate','dehydrate','render','updated','updating','validate','redirect','save','update','create','find','findOrFail','up','down','__construct','__get','__set','__call'];
            if (!in_array($n->name->name, $fw) && !preg_match('/^updated[A-Z]/', $n->name->name)) {
                if ($n->isPrivate() || $n->isProtected()) $n->name->name = uname_obf($n->name->name, $this->m);
            }
        }

        if ($n instanceof Node\Stmt\Property && !($this->lw && $n->isPublic())) {
            foreach ($n->props as $p) {
                if (!in_array($p->name->name, $this->k) && $n->isPrivate()) $p->name->name = uname_obf($p->name->name, $this->m);
            }
        }

        return $n;
    }
}

function evalObf($code, $key, $parser, $printer, &$names) {
    $code = strip_comments($code);
    try {
        $ast = $parser->parse($code);
        if (!$ast) return $code;
        $trav = new NodeTraverser();
        $trav->addVisitor(new FinalVisitor($names));
        $obf = $trav->traverse($ast);
        $result = $printer->prettyPrint($obf);
        $enc = xorEnc($result, $key);
        // loader
        $loader = "<?php \\$_k=\"{$key}\"; \\$_d=base64_decode('{$enc}'); \\$_r=''; for(\\$_i=0; \\$_i<strlen(\\$_d); \\$_i++) \\$_r.=chr(ord(\\$_d[\\$_i])^ord(\\$_k[\\$_i%strlen(\\$_k)])); eval(\\$_r);";
        return $loader;
    } catch (Exception $e) {
        return $code;
    }
}

// --- Main ---
$paths = $config['paths'] ?? ['app','routes'];
$excl = $config['exclusions'] ?? [];
$key = bin2hex(random_bytes(16));
$cnt = 0;
$names = [];

$bk = ($config['backup_prefix'] ?? 'PRODUCTION_BACKUP_') . date('YmdHis');
if (!safeMkdir($bk)) { echo "Impossible de créer backup {$bk}\n"; exit(1); }

// backup
foreach (array_merge($paths, $config['resources'] ?? []) as $p) {
    if (!file_exists($p)) continue;
    echo "📦 Copie: {$p} -> {$bk}/{$p}\n";
    if (!copyDir($p, "{$bk}/{$p}")) {
        echo "Erreur lors de la copie de {$p}\n"; exit(1);
    }
}

// process files
foreach ($paths as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $path = $f->getPathname();
        $skip = false;
        foreach ($excl as $e) { if (str_contains(basename($path), $e)) { $skip = true; break; } }
        if ($skip) { echo "⏭️  {$path}\n"; continue; }
        $code = file_get_contents($path);
        $obf = evalObf($code, $key, $parser, $printer, $names);
        file_put_contents($path, $obf);
        $cnt++;
        if ($cnt % 20 == 0) echo "✅ {$cnt}...\n";
    }
}

// Clean Blade views - comments
$bc = 0;
$viewsDir = 'resources/views';
if (is_dir($viewsDir)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($viewsDir)) as $f) {
        if (str_ends_with($f->getPathname(), '.blade.php')) {
            $c = file_get_contents($f->getPathname());
            $c = preg_replace('/<!--[\s\S]*?-->/', '', $c);
            $c = preg_replace('/\{\{--[\s\S]*?--\}\}/', '', $c);
            file_put_contents($f->getPathname(), $c);
            $bc++;
        }
    }
}

// Self-delete (optional) - comment if you want to keep the script
// unlink(__FILE__);

// Report
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "🏆 OBFUSCATION COMPLETE!\n";
echo "📊 Results:\n";
echo "  ✅ PHP files: {$cnt}\n";
echo "  ✅ Blade views cleaned: {$bc}\n";
echo "  ✅ Variable names: " . count($names) . "\n";
echo "  ✅ Encryption key: {$key}\n\n";
echo "✅ Backup: {$bk}/\n";
