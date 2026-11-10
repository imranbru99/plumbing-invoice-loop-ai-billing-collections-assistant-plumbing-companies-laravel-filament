<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\JobTicket;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\Supplier;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Services\AiBillingService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::query()->updateOrCreate(
            ['code' => 'HQ'],
            ['name' => 'PlumbingInvoiceLoop HQ', 'city' => 'Houston', 'phone' => '+1-713-555-0100', 'is_active' => true]
        );

        $roles = [
            'super_admin' => ['Super Admin', 'admin@demo.local'],
            'owner' => ['Plumbing Owner', 'owner@demo.local'],
            'manager' => ['Operations Lead', 'manager@demo.local'],
            'accountant' => ['Billing Accountant', 'accounts@demo.local'],
            'staff' => ['Office Staff', 'staff@demo.local'],
            'technician' => ['Field Technician', 'tech@demo.local'],
            'customer' => ['Customer Portal', 'customer@demo.local'],
        ];

        foreach ($roles as $role => [$name, $email]) {
            \Spatie\Permission\Models\Role::findOrCreate($role);
            \App\Models\User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => 'password',
                    'user_type' => $role,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ]
            )->assignRole($role);
        }

        $customer = Customer::query()->updateOrCreate(
            ['code' => 'C-1001'],
            ['name' => 'Bayou Property Mgmt', 'city' => 'Houston', 'email' => 'ap@bayou.demo', 'phone' => '+1-713-555-1001', 'balance' => 4820, 'is_active' => true]
        );
        Customer::query()->updateOrCreate(
            ['code' => 'C-1002'],
            ['name' => 'Oakmont Residences', 'city' => 'Katy', 'email' => 'billing@oakmont.demo', 'phone' => '+1-281-555-1002', 'balance' => 1250, 'is_active' => true]
        );

        Supplier::query()->updateOrCreate(['code' => 'S-1001'], ['name' => 'Gulf Coast Pipe Supply', 'city' => 'Pasadena', 'is_active' => true]);
        Lead::query()->updateOrCreate(['code' => 'L-1001'], ['name' => 'New condo repipe bid', 'stage' => 'qualified', 'value' => 22000, 'is_active' => true]);
        Product::query()->updateOrCreate(['code' => 'SKU-PEX'], ['name' => 'PEX Tubing 1/2" Coil', 'sku' => 'PEX-50', 'price' => 68, 'cost' => 32, 'stock' => 90, 'reorder_level' => 15, 'is_active' => true]);
        Product::query()->updateOrCreate(['code' => 'SKU-WC'], ['name' => 'Wax Ring & Closet Flange Kit', 'sku' => 'WC-KIT', 'price' => 42, 'cost' => 18, 'stock' => 55, 'reorder_level' => 10, 'is_active' => true]);
        Employee::query()->updateOrCreate(['code' => 'E-1001'], ['name' => 'Office Coordinator', 'department' => 'Operations', 'designation' => 'Dispatcher', 'salary' => 52000, 'is_active' => true]);
        WorkOrder::query()->updateOrCreate(['code' => 'WO-1001'], ['name' => 'Shop fixture rebuild', 'status' => 'open', 'priority' => 'normal', 'notes' => 'Internal shop work', 'is_active' => true]);

        $tech = Technician::query()->updateOrCreate(
            ['code' => 'T-1001'],
            ['name' => 'Jordan Blake', 'email' => 'jordan@demo.local', 'phone' => '+1-713-555-2001', 'license_no' => 'TX-PLUMB-88421', 'specialty' => 'Drain & sewer', 'status' => 'available', 'is_active' => true]
        );
        Technician::query()->updateOrCreate(
            ['code' => 'T-1002'],
            ['name' => 'Sam Rivera', 'email' => 'sam@demo.local', 'phone' => '+1-713-555-2002', 'license_no' => 'TX-PLUMB-77102', 'specialty' => 'Residential repipe', 'status' => 'on_job', 'is_active' => true]
        );

        $sr = ServiceRequest::query()->updateOrCreate(
            ['code' => 'SR-1001'],
            [
                'name' => 'Burst supply line — Suite 400',
                'customer_id' => $customer->id,
                'priority' => 'high',
                'status' => 'completed',
                'equipment' => '1/2" copper supply',
                'address' => '400 Commerce St, Houston TX',
                'requested_at' => now()->subDays(3),
                'notes' => 'Tenant reports water damage under sink since Monday',
                'is_active' => true,
            ]
        );

        $ticket = JobTicket::query()->updateOrCreate(
            ['code' => 'JT-1001'],
            [
                'name' => 'Supply line + shutoff valve service',
                'service_request_id' => $sr->id,
                'customer_id' => $customer->id,
                'technician_id' => $tech->id,
                'status' => 'completed',
                'priority' => 'high',
                'labor_hours' => 2.5,
                'parts_total' => 134.00,
                'labor_total' => 287.50,
                'scheduled_at' => now()->subDays(2),
                'completed_at' => now()->subDay(),
                'notes' => 'Replaced supply line, installed new shutoff, pressure-tested',
                'is_active' => true,
            ]
        );

        $billing = app(AiBillingService::class);
        $invoice = Invoice::query()->where('code', 'INV-DEMO1')->first();
        if (! $invoice) {
            $invoice = $billing->draftInvoiceFromJob($ticket);
            $invoice->update([
                'code' => 'INV-DEMO1',
                'reminder_consent' => true,
            ]);
            $billing->approveInvoice($invoice, 'Plumbing Owner');
            $reminder = $billing->draftReminder($invoice->fresh());
            $reminder->update(['consent_ok' => true]);
        }

        Invoice::query()->updateOrCreate(
            ['code' => 'INV-DEMO2'],
            [
                'name' => 'Quarterly PM — Oakmont',
                'customer_id' => Customer::query()->where('code', 'C-1002')->value('id'),
                'branch_id' => $branch->id,
                'subtotal' => 920,
                'tax' => 73.60,
                'total' => 993.60,
                'amount_paid' => 0,
                'amount_due' => 993.60,
                'status' => 'approved',
                'payment_status' => 'overdue',
                'issued_at' => now()->subDays(20),
                'due_at' => now()->subDays(5),
                'approved_at' => now()->subDays(19),
                'approved_by' => 'Plumbing Owner',
                'reminder_consent' => true,
                'ai_draft' => 'Preventive maintenance contract invoice — Q3 drain & fixture inspection.',
                'is_active' => true,
            ]
        );
    }
}
