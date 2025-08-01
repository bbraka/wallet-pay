<?php

namespace Tests\Browser\Merchant;

use App\Models\Order;
use App\Models\User;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PendingTransfersTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected User $sender;
    protected User $receiver;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create merchant role
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'merchant']);
        
        // Create test users with initial wallet balances
        $this->sender = User::factory()->create([
            'name' => 'John Sender',
            'email' => 'sender@example.com',
            'password' => bcrypt('password'),
            'wallet_amount' => 1000.00
        ]);
        
        $this->receiver = User::factory()->create([
            'name' => 'Jane Receiver',
            'email' => 'receiver@example.com',
            'password' => bcrypt('password'),
            'wallet_amount' => 500.00
        ]);

        // Assign merchant role to both users
        $this->sender->assignRole('merchant');
        $this->receiver->assignRole('merchant');

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
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $this->receiver->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance');

            // Already on wallet page after login

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
    public function user_can_see_and_reject_pending_transfer()
    {
        // Create a pending transfer from sender to receiver
        $pendingOrder = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 150.00,
            'title' => 'Service Payment',
            'description' => 'Payment for consulting services'
        ]);

        $this->browse(function (Browser $browser) use ($pendingOrder) {
            // Login as receiver
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $this->receiver->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance');

            // Already on wallet page after login

            // Check that pending transfer section is visible
            $browser->assertSee('Pending Transfers')
                    ->assertSee('Service Payment')
                    ->assertSee('$150.00')
                    ->assertSee('John Sender');

            // Reject the transfer and handle confirmation dialog
            $browser->press('Reject')
                    ->waitForDialog(5)
                    ->acceptDialog()
                    ->waitUntilMissing('.btn-danger', 10) // Wait for the button to be removed after rejection
                    ->pause(2000); // Allow time for the page to update

            // Verify the transfer is no longer in pending section
            $browser->refresh()
                    ->waitForText('Wallet Balance')
                    ->assertDontSee('Service Payment'); // Should not see it in pending transfers anymore

            // Verify wallet balance has NOT been updated (rejection doesn't add funds)
            $this->receiver->refresh();
            $this->assertEquals(500.00, $this->receiver->wallet_amount);

            // Verify order status was updated to cancelled
            $pendingOrder->refresh();
            $this->assertEquals(OrderStatus::CANCELLED, $pendingOrder->status);
        });
    }

    /** @test */
    public function pending_transfers_section_is_hidden_when_no_pending_transfers_exist()
    {
        $this->browse(function (Browser $browser) {
            // Login as receiver (who has no pending transfers)
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $this->receiver->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance');

            // Already on wallet page after login

            // Verify that pending transfers section is not visible
            $browser->assertDontSee('Pending Transfers');
        });
    }

    /** @test */
    public function multiple_pending_transfers_are_displayed_correctly()
    {
        // Create multiple pending transfers from sender to receiver
        $transfer1 = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 100.00,
            'title' => 'First Transfer',
            'description' => 'First payment'
        ]);

        $transfer2 = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 250.00,
            'title' => 'Second Transfer',
            'description' => 'Second payment'
        ]);

        $this->browse(function (Browser $browser) {
            // Login as receiver
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $this->receiver->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance');

            // Already on wallet page after login

            // Check that both pending transfers are visible
            $browser->assertSee('Pending Transfers')
                    ->assertSee('First Transfer')
                    ->assertSee('$100.00')
                    ->assertSee('Second Transfer')
                    ->assertSee('$250.00')
                    ->assertSeeIn('[data-testid="pending-transfers"]', 'John Sender'); // Both should show sender name
        });
    }

    /** @test */
    public function confirm_dialog_shows_correct_transfer_details()
    {
        // Create a pending transfer with specific details
        $pendingOrder = Order::factory()->create([
            'user_id' => $this->sender->id,
            'receiver_user_id' => $this->receiver->id,
            'status' => OrderStatus::PENDING_PAYMENT,
            'order_type' => OrderType::INTERNAL_TRANSFER,
            'amount' => 350.75,
            'title' => 'Consulting Fee',
            'description' => 'Website development consultation'
        ]);

        $this->browse(function (Browser $browser) use ($pendingOrder) {
            // Login as receiver
            $browser->visit('/merchant/login')
                    ->waitFor('.card', 10)
                    ->type('email', $this->receiver->email)
                    ->type('password', 'password')
                    ->press('Sign In')
                    ->waitForLocation('/merchant/wallet', 15)
                    ->assertSee('Wallet Balance');

            // Already on wallet page after login

            // Verify transfer details are shown correctly
            $browser->assertSee('Pending Transfers')
                    ->assertSee('Consulting Fee')
                    ->assertSee('$350.75')
                    ->assertSee('John Sender');

            // Test the confirm dialog by pressing confirm and then canceling
            $browser->press('Confirm')
                    ->waitForDialog(5)
                    ->dismissDialog() // Cancel the confirmation
                    ->pause(1000);

            // Verify the transfer is still pending after canceling confirmation
            $browser->assertSee('Consulting Fee'); // Should still be visible

            // Now actually confirm
            $browser->press('Confirm')
                    ->waitForDialog(5)
                    ->acceptDialog()
                    ->waitUntilMissing('.btn-success', 10);

            // Verify order was confirmed
            $pendingOrder->refresh();
            $this->assertEquals(OrderStatus::COMPLETED, $pendingOrder->status);
        });
    }
}