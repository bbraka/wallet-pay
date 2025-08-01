<?php

namespace Tests\Browser\Merchant;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PendingTransfersWorkingTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $sender;
    protected User $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with initial wallet balances
        $this->sender = User::factory()->create([
            'name' => 'John Sender',
            'email' => 'sender@example.com',
            'password' => bcrypt('password123'),
            'wallet_amount' => 1000.00
        ]);
        
        $this->receiver = User::factory()->create([
            'name' => 'Jane Receiver',
            'email' => 'receiver@example.com', 
            'password' => bcrypt('password123'),
            'wallet_amount' => 500.00
        ]);

        // Create initial transactions to establish wallet balances
        \App\Models\Transaction::create([
            'user_id' => $this->sender->id,
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 1000.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance',
            'created_by' => $this->sender->id,
        ]);
        
        \App\Models\Transaction::create([
            'user_id' => $this->receiver->id,
            'type' => \App\Enums\TransactionType::CREDIT,
            'amount' => 500.00,
            'status' => \App\Enums\TransactionStatus::ACTIVE,
            'description' => 'Initial balance',
            'created_by' => $this->receiver->id,
        ]);
    }

    private function loginUser(Browser $browser, User $user): Browser
    {
        return $browser->visit('/merchant/login')
                      ->waitFor('.login-container', 10)
                      ->type('email', $user->email)
                      ->type('password', 'password123')
                      ->press('Login')
                      ->waitForLocation('/merchant/wallet', 15);
    }

    /** @test */
    public function user_can_see_and_confirm_pending_transfer()
    {
        // Create a pending transfer from sender to receiver
        $pendingOrder = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 200.00,
            'title' => 'Test Transfer Payment',
            'description' => 'Payment for services'
        ]);

        $this->browse(function (Browser $browser) use ($pendingOrder) {
            // Login as receiver
            $this->loginUser($browser, $this->receiver)
                 ->assertSee('Wallet Balance');

            // Check that pending transfer section is visible
            $browser->assertSee('Pending Transfers')
                    ->assertSee('Test Transfer Payment')
                    ->assertSee('$200.00')
                    ->assertSee('John Sender');

            // Confirm the transfer
            $browser->press('Confirm')
                    ->waitUntilMissing('.btn-success', 10) // Wait for the button to be removed after confirmation
                    ->pause(2000); // Allow time for the page to update

            // Verify the transfer is no longer in pending section
            $browser->refresh()
                    ->waitForText('Wallet Balance')
                    ->assertDontSee('Test Transfer Payment'); // Should not see it in pending transfers anymore

            // Verify wallet balance has been updated
            $this->receiver->refresh();
            $this->assertEquals(700.00, $this->receiver->wallet_amount);

            // Verify order status was updated
            $pendingOrder->refresh();
            $this->assertEquals(OrderStatus::COMPLETED, $pendingOrder->status);
        });
    }

    /** @test */
    public function pending_transfers_section_is_hidden_when_no_pending_transfers_exist()
    {
        $this->browse(function (Browser $browser) {
            // Login as receiver (who has no pending transfers)
            $this->loginUser($browser, $this->receiver)
                 ->assertSee('Wallet Balance');

            // Verify that pending transfers section is not visible
            $browser->assertDontSee('Pending Transfers');
        });
    }
}