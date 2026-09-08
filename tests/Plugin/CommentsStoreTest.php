<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Wawoo\Plugin\Comments\Store;

final class CommentsStoreTest extends TestCase
{
    private string $slug;

    protected function setUp(): void
    {
        $this->slug = 'test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $f = Store::file($this->slug);
        if (is_file($f)) @unlink($f);
    }

    public function testAddAndApprove(): void
    {
        $id = Store::add($this->slug, [
            'author' => 'alice', 'body' => 'hello',
            'created_at' => time(), 'status' => 'pending',
        ]);
        $this->assertStringStartsWith('c_', $id);
        $this->assertCount(0, Store::approved($this->slug));
        $this->assertTrue(Store::updateStatus($this->slug, $id, 'approved'));
        $this->assertCount(1, Store::approved($this->slug));
    }

    public function testUpdateStatusUnknown(): void
    {
        $this->assertFalse(Store::updateStatus($this->slug, 'c_nope', 'approved'));
    }

    public function testDelete(): void
    {
        $id = Store::add($this->slug, ['author' => 'b', 'body' => 'x', 'created_at' => time(), 'status' => 'pending']);
        $this->assertTrue(Store::delete($this->slug, $id));
        $this->assertCount(0, Store::approved($this->slug));
    }

    public function testDeleteUnknown(): void
    {
        $this->assertFalse(Store::delete($this->slug, 'c_nope'));
    }

    public function testAllPendingGroupsAcrossSlugs(): void
    {
        $slug2 = 'test-' . bin2hex(random_bytes(4));
        try {
            Store::add($this->slug, ['author' => 'a', 'body' => 'a', 'created_at' => time(), 'status' => 'pending']);
            Store::add($slug2,         ['author' => 'b', 'body' => 'b', 'created_at' => time()+1, 'status' => 'approved']);
            $all = Store::allPending();
            $this->assertGreaterThanOrEqual(2, count($all));
            // Newest first
            $this->assertGreaterThanOrEqual($all[1]['created_at'], $all[0]['created_at']);
        } finally {
            @unlink(Store::file($slug2));
        }
    }
}
