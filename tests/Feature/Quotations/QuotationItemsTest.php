<?php

namespace Tests\Feature\Quotations;

use App\Models\Deal;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationItemsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function deal(User $user): Deal
    {
        return Deal::forceCreate(['title' => 'D', 'value' => 0, 'user_id' => $user->id]);
    }

    private function payload(Deal $deal, array $items): array
    {
        return ['deal_id' => $deal->id, 'title' => 'Test', 'description' => null, 'vat_rate' => 24, 'valid_until' => null, 'terms' => null, 'notes' => null, 'items' => $items];
    }

    public function test_long_rows_and_text_rows_are_saved_in_order(): void
    {
        $user = User::factory()->create();
        $deal = $this->deal($user);
        $long = trim(str_repeat('Espo crm liides: teenuste seadistusleht. ', 20)); // > 255 chars

        $this->actingAs($user)->post(route('quotations.store'), $this->payload($deal, [
            ['type' => 'text', 'description' => $long],
            ['type' => 'item', 'description' => $long, 'quantity' => 2, 'unit' => 'h', 'unit_price' => 50],
        ]))->assertRedirect();

        $q = Quotation::with('items')->firstOrFail();
        $this->assertCount(2, $q->items);
        $this->assertSame('text', $q->items[0]->type);
        $this->assertSame($long, $q->items[0]->description);
        $this->assertEquals(0, $q->items[0]->subtotal);
        $this->assertSame('item', $q->items[1]->type);
        $this->assertEquals(100, $q->subtotal);
        $this->assertEquals(124, $q->total);

        $this->actingAs($user)->get(route('quotations.show', $q))->assertOk()->assertSee('Espo crm liides');
        $this->actingAs($user)->get(route('quotations.edit', $q))->assertOk();
        $this->actingAs($user)->get(route('quotations.pdf', $q))->assertOk();

        $this->actingAs($user)->put(route('quotations.update', $q), $this->payload($deal, [
            ['type' => 'item', 'description' => 'A', 'quantity' => 1, 'unit' => 'tk', 'unit_price' => 10],
            ['type' => 'text', 'description' => 'Selgitus'],
        ]))->assertRedirect();
        $q->refresh()->load('items');
        $this->assertSame(['item', 'text'], $q->items->pluck('type')->all());
        $this->assertEquals(10, $q->subtotal);
    }

    public function test_description_formatting_is_safe(): void
    {
        $html = (string) (new \App\Models\QuotationItem(['description' => "## Osa\n**paks** <script>x</script>\n- a"]))->formattedDescription();
        $this->assertStringContainsString('<h2>Osa</h2>', $html);
        $this->assertStringContainsString('<strong>paks</strong>', $html);
        $this->assertStringContainsString('<li>a</li>', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_price_row_still_requires_price(): void
    {
        $user = User::factory()->create();
        $deal = $this->deal($user);
        $this->actingAs($user)
            ->post(route('quotations.store'), $this->payload($deal, [['type' => 'item', 'description' => 'X']]))
            ->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_price']);
    }
}
