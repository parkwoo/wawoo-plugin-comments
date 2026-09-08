<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Comments\InjectForm;

final class CommentsInjectFormTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['wawoo_comment_error'], $_SESSION['wawoo_comment_ok']);
    }

    public function testFormActionUsesPrettyPostUrl(): void
    {
        ob_start();
        (new InjectForm())->handle(['post' => ['slug' => 'welcome']]);
        $html = ob_get_clean();

        $this->assertStringContainsString('action="' . BASE_URL . 'post/welcome#comments"', $html);
        $this->assertStringNotContainsString('?post=', $html);
        $this->assertStringContainsString('name="post_slug" value="welcome"', $html);
    }

    public function testFormHiddenForNonPostPages(): void
    {
        ob_start();
        (new InjectForm())->handle(['posts' => []]);
        $html = ob_get_clean();
        $this->assertSame('', $html);
    }
}
