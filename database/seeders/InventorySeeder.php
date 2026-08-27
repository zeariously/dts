<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $items = [

            /*
            |--------------------------------------------------------------------------
            | SUPPLIES
            |--------------------------------------------------------------------------
            */

            [
                'category' => 'supplies',
                'item' => 'DOST LETTER HEAD',
                'unit' => 'REAM',
                'fixed_value' => 20,
                'available' => '11',
                'quarters' => ['q1', 'q2', 'q3'],
                'ppmp' => "10 reams Q1 to Q3\nTotal of 30 reams for 2027",
                'remarks' => 'edit in PPMP',
            ],

            [
                'category' => 'supplies',
                'item' => 'BOND PAPER, Multicopy Legal',
                'unit' => 'REAM',
                'fixed_value' => 20,
                'available' => '5',
                'quarters' => ['q1', 'q2', 'q3'],
                'ppmp' => "20 reams Q1 to Q3\nTotal of 60 reams for 2027",
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'BOND PAPER, Multicopy A4',
                'unit' => 'REAM',
                'fixed_value' => 20,
                'available' => '53',
                'quarters' => ['q1', 'q2', 'q3'],
                'ppmp' => "80 reams for Q1 to Q3\nTotal of 240 reams for 2027",
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'BOND PAPER, A3',
                'unit' => 'REAM',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1'],
                'ppmp' => '2',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'PAPER, COLORED (RED)',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '4',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'PAPER, COLORED (BLUE)',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '4',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'PAPER, COLORED (YELLOW)',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STICKER PAPER, A4',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '3',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '20 (5 packs/quarter)',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'PHOTO PAPER',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '10',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'PAPER, VELLUM',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '9',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '20 (5 packs/quarter)',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'FILE FOLDER, LEGAL (brown)',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '26',
                'quarters' => ['q1'],
                'ppmp' => '4 packs',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'FILE FOLDER, A4 (brown)',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '6',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'EXPANDED FOLDER',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'BROWN ENVELOPE, LEGAL',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'BROWN ENVELOPE, A4',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'COIN ENVELOPES',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '2',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'ARCO FOLDER DIVIDER',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '51',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STENO NOTEBOOK',
                'unit' => 'PIECE',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'RECORD BOOK',
                'unit' => 'PIECE',
                'fixed_value' => 20,
                'available' => '10',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'CLEARBOOK, LEGAL (BLACK)',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '2',
                'quarters' => ['q1', 'q3'],
                'ppmp' => '10 (5 pcs for Q1 & Q3)',
                'remarks' => 'edit in PPMP',
            ],

            [
                'category' => 'supplies',
                'item' => 'FLAGLETTES w/ SIGN HERE',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '12',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '500 (125 packs/quarter)',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STICKY NOTES FLAGLETTES (COLORED)',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '3',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STICKY NOTE PAD, 4x6 inches',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '9 pads only',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STICKY NOTE PAD, 4x4 inches',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '2 pads only',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '20 (5 packs/quarter)',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STICKY NOTES, 2x3 inches',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '20 pads only',
                'quarters' => [],
                'ppmp' => '0',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'STICKY NOTES, 3x3 inches',
                'unit' => 'PACK',
                'fixed_value' => 20,
                'available' => '1 pad only',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '20 (5 packs/quarter)',
                'remarks' => '',
            ],

            [
                'category' => 'supplies',
                'item' => 'PAPER CLIP, Vinyl Plastic Coated, 33 mm',
                'unit' => 'BOX',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '20 (5 boxes/quarter)',
                'remarks' => '',
            ],

            /*
            |--------------------------------------------------------------------------
            | ICT / OTHER ITEMS
            |--------------------------------------------------------------------------
            */

            [
                'category' => 'ict',
                'item' => 'Printer Laser, Monochrome',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1'],
                'ppmp' => '1',
                'remarks' => 'Old unit has been returned to PSS',
            ],

            [
                'category' => 'ict',
                'item' => '27" Computer Monitor',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '3',
                'quarters' => ['q1'],
                'ppmp' => '3',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => '2TB Portable SSD',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '5',
                'quarters' => ['q1'],
                'ppmp' => '5',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Tablet',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '6',
                'quarters' => ['q1'],
                'ppmp' => '8',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'USB-C Adapter',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '4',
                'quarters' => ['q1'],
                'ppmp' => '3',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Web Camera',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '2',
                'quarters' => ['q1'],
                'ppmp' => '1',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Digital Voice Recorder',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1'],
                'ppmp' => '2',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Laptop with Licensed Software & Accessories',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '9',
                'quarters' => ['q1'],
                'ppmp' => '12',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Zoom Subscription',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '1',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'AI Subscription (Nota)',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '1',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Canva Subscription',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '1',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Internet Subscription (Starlink)',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '3',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '3',
                'remarks' => '1 per month; depend on the need',
            ],

            [
                'category' => 'ict',
                'item' => 'Mentimeter',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1'],
                'ppmp' => '1',
                'remarks' => 'for NSTW',
            ],

            [
                'category' => 'ict',
                'item' => 'Google Storage',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '1',
                'remarks' => '',
            ],

            [
                'category' => 'ict',
                'item' => 'Purchase of Plane Tickets for DOST Officials and Secretariat for the conduct of 2027 Call Conference',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '3',
                'quarters' => ['q1'],
                'ppmp' => '3',
                'remarks' => 'North Luzon, Visayas & Mindanao',
            ],

            [
                'category' => 'ict',
                'item' => 'Purchase of Plane Tickets for various travel of SPD Staff',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '4',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '4',
                'remarks' => '1 per quarter',
            ],

            [
                'category' => 'ict',
                'item' => 'Lease of Venue, Meals & Accommodation for the conduct of DOST-GIA EXECOM for Policy and Planning Meeting',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '4',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '4',
                'remarks' => '1 per quarter',
            ],

            [
                'category' => 'ict',
                'item' => 'Catering Service for the conduct of DOST-GIA EXECOM Meetings',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '12',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '12',
                'remarks' => '1 per month',
            ],

            [
                'category' => 'ict',
                'item' => 'Meals for other SPD meetings',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '10',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '12',
                'remarks' => '1 per month',
            ],

            [
                'category' => 'ict',
                'item' => 'Transportation Expenses (fares)',
                'unit' => 'PAX',
                'fixed_value' => 20,
                'available' => '12',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '12',
                'remarks' => '5 per month',
            ],

            [
                'category' => 'ict',
                'item' => 'Rental of Photocopying Machine',
                'unit' => 'LOT',
                'fixed_value' => 20,
                'available' => '1',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '12',
                'remarks' => '1 year contract',
            ],

            [
                'category' => 'ict',
                'item' => 'Printing & Publication',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '4',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '4',
                'remarks' => '1 per quarter',
            ],

            [
                'category' => 'ict',
                'item' => 'Mobile Pedestal',
                'unit' => 'UNIT',
                'fixed_value' => 20,
                'available' => '3',
                'quarters' => ['q1', 'q2', 'q3', 'q4'],
                'ppmp' => '3',
                'remarks' => '3 per year',
            ],
        ];

        foreach ($items as $item) {
            InventoryItem::updateOrCreate(
                [
                    'category' => $item['category'],
                    'item' => $item['item'],
                    'unit' => $item['unit'],
                ],
                [
                    'fixed_value' => $item['fixed_value'],
                    'available' => $item['available'],
                    'quarters' => $item['quarters'],
                    'ppmp' => $item['ppmp'],
                    'remarks' => $item['remarks'],
                ]
            );
        }
    }
}