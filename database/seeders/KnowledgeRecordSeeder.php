<?php

namespace Database\Seeders;

use App\Enums\KnowledgeCategory;
use App\Enums\KnowledgeStatus;
use App\Models\KnowledgeRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

class KnowledgeRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $superAdmin = User::query()->where('email', 'superadmin@bamcom.ng')->first()
            ?? User::query()->first();

        $records = [
            // 1. Company Information
            [
                'title' => 'Bamcom Properties Corporate Profile & Legal Incorporation',
                'category' => KnowledgeCategory::CompanyInformation,
                'status' => KnowledgeStatus::Active,
                'priority' => 100,
                'content' => "Bamcom Properties & Real Estate Ltd is an accredited real estate development firm fully incorporated with the Corporate Affairs Commission (CAC) of Nigeria (RC: 1849204). Founded with a mission to deliver secure, titled, and high-yield property assets, Bamcom specializes in master-planned residential estates, commercial corridors, and agricultural wealth projects across Lagos and Ogun State.\n\nCorporate Headquarters: Plot 12, Admiralty Way, Lekki Phase 1, Lagos, Nigeria.\nOfficial Website: https://bamcom.ng\nOfficial Inquiries: hello@bamcom.ng | +234 800 BAMCOM (0800 226 266)\nAll bank transactions are conducted solely via verified corporate bank accounts under the registered corporate name 'Bamcom Properties & Real Estate Ltd'. We never accept payments into personal accounts.",
                'keywords' => ['cac', 'rc number', 'incorporation', 'office address', 'headquarters', 'bank account', 'legitimacy', 'who is bamcom'],
                'effective_date' => now()->subMonths(6),
                'expiration_date' => null,
            ],
            [
                'title' => 'Executive Leadership & Advisory Board',
                'category' => KnowledgeCategory::CompanyInformation,
                'status' => KnowledgeStatus::Active,
                'priority' => 80,
                'content' => "Bamcom Properties is spearheaded by veteran civil engineers, urban planners, and seasoned investment advisors with over 25 cumulative years in Nigerian real estate.\n- Managing Director / CEO: Engr. Babatunde Alabi, FNSE\n- Executive Director of Project Development: Arch. Folake Adeleke\n- Chief Financial Officer: Samuel Okonkwo, FCA\nOur team coordinates directly with the Lagos State Ministry of Physical Planning & Urban Development (LASPPPA) and the New Towns Development Authority (NTDA).",
                'keywords' => ['leadership', 'ceo', 'directors', 'founders', 'management', 'engineers'],
                'effective_date' => now()->subMonths(6),
                'expiration_date' => null,
            ],

            // 2. FAQs
            [
                'title' => 'Understanding Land Titles: Certificate of Occupancy, Governor\'s Consent, and Gazette',
                'category' => KnowledgeCategory::Faq,
                'status' => KnowledgeStatus::Active,
                'priority' => 95,
                'content' => "What is the difference between land titles at Bamcom estates?\n1. Governor's Consent: An official confirmation signed by the State Governor validating the legal transfer of land from one party to another. Considered the most secure land title in Lagos.\n2. Certificate of Occupancy (C of O): A government-issued document granting the holder statutory right of occupancy for 99 years.\n3. Gazette / Excision: Government publication detailing land partitioned and released to traditional indigenous communities, safe for private acquisition and title perfection.\n4. Registered Survey: A cadastral boundary blueprint lodged with the Surveyor General's office, verifying coordinates fall free from government acquisition.",
                'keywords' => ['c of o', 'governors consent', 'title', 'gazette', 'excision', 'registered survey', 'freehold', 'land title'],
                'effective_date' => now()->subMonths(3),
                'expiration_date' => null,
            ],
            [
                'title' => 'Timeline for Physical Allocation and Documentation',
                'category' => KnowledgeCategory::Faq,
                'status' => KnowledgeStatus::Active,
                'priority' => 90,
                'content' => "What documents do I get, and when?\n- Upon initial deposit (20-30%): You receive an Official Payment Receipt and Provisional Allocation Letter / Contract of Sale within 48 hours.\n- Upon completing 50% payment: Physical plot reservation and layout beacon allocation are assigned.\n- Upon 100% full payment: You receive the Deed of Assignment, Registered Survey Plan, and physical handover on site within 14 business days.",
                'keywords' => ['allocation', 'documents', 'deed of assignment', 'survey', 'receipt', 'contract of sale', 'when do i get my land'],
                'effective_date' => now()->subMonths(3),
                'expiration_date' => null,
            ],

            // 3. Sales Information
            [
                'title' => '2026 Smart Wealth Promo & Outright Purchase Incentives',
                'category' => KnowledgeCategory::SalesInformation,
                'status' => KnowledgeStatus::Active,
                'priority' => 85,
                'content' => "Current Sales Incentives for All Estates:\n- Outright Payment Discount: 10% instant rebate when payment is settled in full within 30 days.\n- Development Levy Freeze: Zero price increments on development levies for subscribers who initiate transactions within the promotional quarter.\n- Commercial Plot Bundle: Purchase 5 residential plots in a single batch and receive 1 commercial plot allocation at 50% discount.",
                'keywords' => ['promo', 'discount', 'outright', 'incentive', 'rebate', 'deal', 'savings', 'special offer'],
                'effective_date' => now()->subDays(10),
                'expiration_date' => now()->addMonths(6),
            ],

            // 4. Property Knowledge
            [
                'title' => 'Ibeju-Lekki & Epe Economic Growth Corridor Analysis',
                'category' => KnowledgeCategory::PropertyKnowledge,
                'status' => KnowledgeStatus::Active,
                'priority' => 90,
                'content' => "Why invest in the Ibeju-Lekki / Epe Corridor?\n1. Dangote Refinery & Petrochemical Complex: Employing over 50,000 workers, creating high demand for rental housing.\n2. Lekki Deep Sea Port: Nigeria's deepest sea port driving multi-billion dollar trade logistics along the Lekki-Epe expressway.\n3. Lekki International Airport: Proposed airport triggering rapid land appreciation within a 15km perimeter.\n4. Lagos-Calabar Coastal Highway: Unlocks direct waterfront connectivity, projecting 300% to 500% capital appreciation over 3 to 5 years.",
                'keywords' => ['ibeju lekki', 'epe', 'refinery', 'deep sea port', 'appreciation', 'roi', 'growth', 'corridor', 'investment'],
                'effective_date' => now()->subMonths(2),
                'expiration_date' => null,
            ],

            // 5. Inspection Policies
            [
                'title' => 'Physical Site Inspection Schedules & Logistics Protocol',
                'category' => KnowledgeCategory::InspectionPolicy,
                'status' => KnowledgeStatus::Active,
                'priority' => 95,
                'content' => "Site Inspection Guidelines:\n- Inspection Days: Monday through Saturday (Closed on Sundays).\n- Time Slots: Morning Session at 10:00 AM | Afternoon Session at 2:00 PM.\n- Pickup Point: Bamcom Headquarters, Plot 12, Admiralty Way, Lekki Phase 1.\n- Inspection Vehicles: Air-conditioned company escort vehicles are provided free of charge to prospective buyers.\n- Booking Policy: Must be booked at least 24 hours in advance to secure seat allocation and vehicle readiness.\n- Diaspora Virtual Tours: Available via live WhatsApp Video Call or 4K drone walkthrough upon request.",
                'keywords' => ['inspection', 'visit', 'pickup', 'tour', 'car', 'booking', 'saturday', 'virtual inspection', 'site visit'],
                'effective_date' => now()->subMonths(3),
                'expiration_date' => null,
            ],

            // 6. Payment Policies
            [
                'title' => 'Flexible Installment Spreads & Default Grace Periods',
                'category' => KnowledgeCategory::PaymentPolicy,
                'status' => KnowledgeStatus::Active,
                'priority' => 95,
                'content' => "Bamcom Structured Payment Terms:\n- Minimum Initial Deposit: 20% to 30% of total property value.\n- Spread Options: 3 Months (Interest-Free) | 6 Months (Interest-Free) | 12 Months (Standard plan).\n- Banking Protocol: All deposits must be paid into designated company escrow accounts. Cash is strictly rejected.\n- Grace Period: Clients experiencing payment hiccups are granted a 21-day grace period with written notice to sales management before any surcharge or reassignment occurs.",
                'keywords' => ['payment plan', 'installment', 'deposit', 'spread', 'terms', 'grace period', 'escrow', 'interest free'],
                'effective_date' => now()->subMonths(3),
                'expiration_date' => null,
            ],

            // 7. Objection Handling
            [
                'title' => 'Handling Common Buyer Objections: Legitimacy, Price & Title Delays',
                'category' => KnowledgeCategory::ObjectionHandling,
                'status' => KnowledgeStatus::Active,
                'priority' => 100,
                'content' => "Standard Objection Handling Framework:\n1. 'How do I know this is not a scam / Omo-onile wahala?'\n-> Acknowledge the fear: Real estate caution in Lagos is smart. Reassure: All Bamcom land is 100% acquired with perfected titles (Gazette/Governor's Consent) and perimeter perimeter fencing. We provide the coordinate survey numbers so your personal lawyer can search the Lands Bureau at Alausa, Ikeja before paying 1 Naira.\n\n2. 'Why is your price higher than other land in this area?'\n-> Explain Value vs Risk: Cheap land in Ibeju-Lekki often has government acquisition tags or unresolved family disputes. Bamcom properties include dry table land, motorable paved access, solar streetlights, drainage infrastructure, and guaranteed allocation.\n\n3. 'What if I live in the UK/US/Canada, can I buy safely?'\n-> Diaspora Guarantee: Over 40% of Bamcom subscribers are in the diaspora. We offer live video inspection, electronic contract signing, direct courier delivery of original deeds via DHL, and dedicated diaspora liaison reps.",
                'keywords' => ['scam', 'omo onile', 'risk', 'expensive', 'diaspora', 'assurance', 'lawyer', 'alausa', 'objection'],
                'effective_date' => now()->subMonths(3),
                'expiration_date' => null,
            ],

            // 8. Approved Sales Scripts
            [
                'title' => 'Lead Qualification & First Response WhatsApp Scripts',
                'category' => KnowledgeCategory::SalesScript,
                'status' => KnowledgeStatus::Active,
                'priority' => 90,
                'content' => "Approved Opening & Qualification Framework for AI and Sales Reps:\n\n1. Greeting & Rapport:\n'Hello [Name]! Welcome to Bamcom Properties. We are excited to assist you with secure land investments across Lagos. Are you looking for land to build immediately, or a high-growth investment to hold for capital appreciation?'\n\n2. Budget & Location Clarification:\n'We have premium estates in Lekki, Epe, and Ibeju-Lekki with flexible deposits starting from 20%. Which location or budget range do you have in mind?'\n\n3. Inspection Closing Hook:\n'Pictures and videos do not do justice to the rapid road and commercial developments happening right now on site. We have an inspection vehicle heading out this Saturday at 10 AM. Would you like me to reserve a seat for you?'",
                'keywords' => ['script', 'greeting', 'opening', 'qualification', 'closing', 'whatsapp template', 'sales pitch'],
                'effective_date' => now()->subMonths(3),
                'expiration_date' => null,
            ],
        ];

        foreach ($records as $item) {
            KnowledgeRecord::updateOrCreate(
                ['title' => $item['title']],
                array_merge($item, [
                    'created_by' => $superAdmin?->id,
                ])
            );
        }
    }
}
