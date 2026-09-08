<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Comments\AdminPanel;
use Wawoo\Plugin\Comments\Config;
use Wawoo\Plugin\Comments\Form;
use Wawoo\Plugin\Comments\Store;

final class CommentsSecurityTest extends TestCase
{
    private ?string $slug = null;

    protected function setUp(): void
    {
        $this->slug = null;
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($GLOBALS['WAWOO_COMMENTS']);
        self::resetConfig();
    }

    protected function tearDown(): void
    {
        if ($this->slug !== null && is_file(Store::file($this->slug))) {
            @unlink(Store::file($this->slug));
        }
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($GLOBALS['WAWOO_COMMENTS']);
        self::resetConfig();
    }

    public function testCsrfTokenGeneratedOnceAndPersisted(): void
    {
        $out = $this->runChild(<<<'PHP'
$_SESSION = [];
$t1 = \Wawoo\Plugin\Comments\Form::csrfToken();
$t2 = \Wawoo\Plugin\Comments\Form::csrfToken();
echo (preg_match('/^[0-9a-f]{32}$/', $t1) ? 'HEX=OK' : 'HEX=BAD') . "\n";
echo ($t1 === $t2 ? 'STABLE=OK' : 'STABLE=BAD') . "\n";
echo ($t1 === ($_SESSION['wawoo_csrf'] ?? '') ? 'SESSION=OK' : 'SESSION=BAD') . "\n";
PHP, 'stub-post');
        $this->assertStringContainsString('HEX=OK', $out);
        $this->assertStringContainsString('STABLE=OK', $out);
        $this->assertStringContainsString('SESSION=OK', $out);
    }

    public function testCsrfValidCompareHelper(): void
    {
        $_SESSION['wawoo_csrf'] = 'session-token';
        $this->assertTrue(Form::csrfValid('session-token'));
        $this->assertFalse(Form::csrfValid('other'));
        $this->assertFalse(Form::csrfValid(''));
        $this->assertFalse(Form::csrfValid(null));
    }

    public function testModerateWithWrongCsrfRejectedWith403(): void
    {
        $out = $this->runChild($this->moderateWrongCsrfBody(), 'stub-post');
        $this->assertStringContainsString('Forbidden', $out);
        $this->assertStringContainsString('RC=403', $out);
        $this->assertStringNotContainsString('SURVIVED', $out);
    }

    public function testSubmitToNonexistentPostIsRefusedWithoutFile(): void
    {
        $this->slug = 'no-such-post-' . bin2hex(random_bytes(6));
        $out = $this->runChild($this->submitBogusBody(), $this->slug);
        $this->assertStringNotContainsString('SURVIVED', $out);
        $this->assertFileDoesNotExist(Store::file($this->slug));
    }

    public function testAdminGateAcceptsWawooAdminAuthed(): void
    {
        $_SESSION['wawoo_admin_authed'] = true;
        $this->assertTrue(self::invokeIsAdmin(Form::class));
        $this->assertTrue(self::invokeIsAdmin(AdminPanel::class));
    }

    public function testAdminGateAcceptsBuiltInAdminKey(): void
    {
        $_SESSION['wawoo_admin'] = true;
        $this->assertTrue(self::invokeIsAdmin(Form::class));
        $this->assertTrue(self::invokeIsAdmin(AdminPanel::class));
    }

    public function testAdminGateRejectsEmptySession(): void
    {
        $this->assertFalse(self::invokeIsAdmin(Form::class));
        $this->assertFalse(self::invokeIsAdmin(AdminPanel::class));
    }

    public function testConfigDefaultsComeFromPluginJson(): void
    {
        $cfg = Config::all();
        $this->assertSame('manual', $cfg['moderation']);
        $this->assertSame(2000, $cfg['max_length']);
        $this->assertSame(2, $cfg['min_length']);
        $this->assertSame(20, $cfg['per_page']);
        $this->assertSame(5, $cfg['rate_limit_per_hour']);
    }

    public function testConfigGlobalsOverridePluginJson(): void
    {
        $GLOBALS['WAWOO_COMMENTS'] = ['max_length' => 500, 'moderation' => 'auto'];
        $cfg = Config::all();
        $this->assertSame(500, $cfg['max_length']);
        $this->assertSame('auto', $cfg['moderation']);
        $this->assertSame(20, $cfg['per_page']);
    }

    private static function invokeIsAdmin(string $class): bool
    {
        $m = new ReflectionMethod($class, 'isAdmin');
        $m->setAccessible(true);
        return (bool)$m->invoke(null);
    }

    private static function resetConfig(): void
    {
        $p = new ReflectionProperty(Config::class, 'cache');
        $p->setAccessible(true);
        $p->setValue(null, null);
    }

    private function runChild(string $body, string $slug): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'wccs');
        if ($tmp === false) {
            $this->markTestSkipped('Cannot create temp file for subprocess.');
        }
        $file = $tmp . '.php';
        $bootstrap = dirname(__DIR__, 2) . '/tests/bootstrap.php';
        $sessionDir = sys_get_temp_dir() . '/wawoo_sess_' . bin2hex(random_bytes(4));
        mkdir($sessionDir, 0755, true);
        file_put_contents($file, "<?php\n"
            . "session_save_path(" . var_export($sessionDir, true) . ");\n"
            . "require \$argv[1];\n"
            . "register_shutdown_function(function () { fwrite(STDOUT, '\\n__EXIT__\\n'); });\n"
            . $body . "\n");
        try {
            if (!function_exists('shell_exec')) {
                $this->markTestSkipped('shell_exec is not available.');
            }
            $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file)
                 . ' ' . escapeshellarg($bootstrap) . ' ' . escapeshellarg($slug);
            $out = shell_exec($cmd . ' 2>&1; echo "RC_CODE=$?"');
            if ($out === null || $out === '') {
                $this->fail('Subprocess produced no output.');
            }
            return (string)$out;
        } finally {
            @unlink($file);
        }
    }

    private function submitBogusBody(): string
    {
        return <<<'PHP'
$_SESSION = ['wawoo_csrf' => 'tok-123'];
$_POST = [
    'wawoo_action' => 'comment_submit',
    'post_slug'    => $argv[2],
    'csrf'         => 'tok-123',
    'name'         => 'Alice',
    'email'        => '',
    'url'          => '',
    'body'         => 'Hello there',
];
$m = new ReflectionMethod(\Wawoo\Plugin\Comments\Form::class, 'submit');
$m->setAccessible(true);
$m->invoke(null);
echo "SURVIVED\n";
PHP;
    }

    private function moderateWrongCsrfBody(): string
    {
        return <<<'PHP'
$_SESSION = ['wawoo_admin' => true, 'wawoo_csrf' => 'tok-123'];
$_POST = [
    'wawoo_action' => 'comment_moderate',
    'post_slug'    => $argv[2],
    'comment_id'   => 'c_123',
    'op'           => 'delete',
    'csrf'         => 'wrong-token',
];
register_shutdown_function(function () {
    fwrite(STDOUT, 'RC=' . http_response_code());
});
$m = new ReflectionMethod(\Wawoo\Plugin\Comments\Form::class, 'moderate');
$m->setAccessible(true);
$m->invoke(null);
echo "SURVIVED\n";
PHP;
    }
}
