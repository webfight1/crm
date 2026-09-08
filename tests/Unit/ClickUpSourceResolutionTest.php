<?php

namespace Tests\Unit;

use App\Services\ClickUpService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ClickUp addresses the same list through several URL shapes, and getting this
 * wrong is silent — you get a 404 or, worse, the id "li".
 */
class ClickUpSourceResolutionTest extends TestCase
{
    private ClickUpService $clickup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clickup = new ClickUpService('pk_test');
    }

    public static function sources(): array
    {
        return [
            'list, short form'   => ['https://app.clickup.com/9015331367/v/li/901519221606', '901519221606', true],
            'list, long form'    => ['https://app.clickup.com/9015331367/v/l/li/901523799837', '901523799837', true],
            'list with query'    => ['https://app.clickup.com/9015331367/v/l/li/901523799837?block=x', '901523799837', true],
            'chat view'          => ['https://app.clickup.com/9015331367/v/cn/8cnp2h7-1595', '8cnp2h7-1595', false],
            'list view (not li)' => ['https://app.clickup.com/9015331367/v/l/8cnp2h7-2', '8cnp2h7-2', false],
            'board view'         => ['https://app.clickup.com/9015331367/v/b/8cnp2h7-9', '8cnp2h7-9', false],
            'bare id'            => ['8cnp2h7-1595', '8cnp2h7-1595', false],
        ];
    }

    #[DataProvider('sources')]
    public function test_it_resolves_every_url_shape(string $url, string $expectedId, bool $expectedIsList): void
    {
        [$id, $isList] = $this->clickup->resolveSource($url);

        $this->assertSame($expectedId, $id);
        $this->assertSame($expectedIsList, $isList);
    }

    public function test_a_bare_id_can_be_forced_to_a_list(): void
    {
        [$id, $isList] = $this->clickup->resolveSource('901523799837', forceList: true);

        $this->assertSame('901523799837', $id);
        $this->assertTrue($isList);
    }

    public function test_it_rejects_a_clickup_url_with_no_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->clickup->resolveSource('https://app.clickup.com/9015331367/home');
    }
}
