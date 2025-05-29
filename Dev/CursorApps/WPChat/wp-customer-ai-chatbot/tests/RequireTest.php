<?php

echo "[RequireTest] Start\n";
use PHPUnit\Framework\TestCase;
echo "[RequireTest] Before require_once\n";
require_once __DIR__ . '/../includes/retrieval/class-wcac-chatbot-rules.php';
echo "[RequireTest] After require_once\n";
class RequireTest extends TestCase
{
    public function testRequire()
    {
        $this->assertTrue(true);
    }
}
