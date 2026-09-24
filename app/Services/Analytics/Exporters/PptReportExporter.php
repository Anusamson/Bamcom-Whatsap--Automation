<?php

namespace App\Services\Analytics\Exporters;

use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

class PptReportExporter
{
    /**
     * Generate and return an executive PowerPoint presentation (.pptx / .ppt).
     *
     * @param  array<string, mixed>  $data
     */
    public function export(array $data): Response
    {
        $period = $data['dateRange']['period'] ?? '30d';
        $periodLabel = htmlspecialchars($data['dateRange']['label'] ?? 'Custom');
        $dateFrom = htmlspecialchars($data['dateRange']['date_from'] ?? '');
        $dateTo = htmlspecialchars($data['dateRange']['date_to'] ?? '');
        $timestamp = now()->format('Ymd_His');
        $filename = "bamcom_analytics_report_{$period}_{$timestamp}.pptx";

        $kpis = $data['kpis'] ?? [];
        $sources = $data['reports']['lead_source']['sources'] ?? [];
        $stages = $data['reports']['pipeline_funnel']['stages'] ?? [];
        $sp = $data['reports']['sales_performance'] ?? [];
        $agents = $data['reports']['agent_performance']['agents'] ?? [];
        $insp = $data['reports']['inspection_conversion'] ?? [];
        $campaigns = $data['reports']['campaign_performance']['campaigns'] ?? [];
        $ai = $data['reports']['ai_conversations'] ?? [];
        $handovers = $data['reports']['human_handovers']['triggers'] ?? [];

        // Build Slides
        $slides = [];

        // Slide 1: Title Slide
        $slides[] = $this->createTitleSlide($periodLabel, $dateFrom, $dateTo);

        // Slide 2: Executive KPI Scorecard
        $slides[] = $this->createKpiSlide($kpis, $periodLabel);

        // Slide 3: Pipeline Funnel Progression
        $slides[] = $this->createFunnelSlide($stages);

        // Slide 4: Sales Performance & Deal Velocity
        $slides[] = $this->createSalesSlide($sp);

        // Slide 5: Representative Leaderboard
        $slides[] = $this->createAgentSlide($agents);

        // Slide 6: Lead Sources & WhatsApp Broadcasts
        $slides[] = $this->createMarketingSlide($sources, $campaigns);

        // Slide 7: AI Automation & Human Escalations
        $slides[] = $this->createAiSlide($ai, $handovers);

        // Package into PPTX ZipArchive
        $tempFile = tempnam(sys_get_temp_dir(), 'pptx_');
        $zip = new ZipArchive;
        $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // 1. [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'.
            '<Default Extension="xml" ContentType="application/xml"/>'.
            '<Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>'.
            '<Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>'.
            '<Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>'.
            '<Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>';

        foreach ($slides as $i => $s) {
            $slideNum = $i + 1;
            $contentTypes .= '<Override PartName="/ppt/slides/slide'.$slideNum.'.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slide+xml"/>';
        }
        $contentTypes .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // 2. _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>'.
            '</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // 3. ppt/presentation.xml
        $pres = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:sldMasterIdLst><p:sldMasterId id="2147483648" r:id="rId1"/></p:sldMasterIdLst>'.
            '<p:sldIdLst>';
        foreach ($slides as $i => $s) {
            $slideId = 256 + $i;
            $rId = 'rId'.($i + 2);
            $pres .= '<p:sldId id="'.$slideId.'" r:id="'.$rId.'"/>';
        }
        $pres .= '</p:sldIdLst><p:sldSz cx="9144000" cy="5143500"/><p:notesSz cx="6858000" cy="9144000"/></p:presentation>';
        $zip->addFromString('ppt/presentation.xml', $pres);

        // 4. ppt/_rels/presentation.xml.rels
        $presRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>';
        foreach ($slides as $i => $s) {
            $slideNum = $i + 1;
            $rId = 'rId'.($i + 2);
            $presRels .= '<Relationship Id="'.$rId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide" Target="slides/slide'.$slideNum.'.xml"/>';
        }
        $presRels .= '</Relationships>';
        $zip->addFromString('ppt/_rels/presentation.xml.rels', $presRels);

        // 5. Theme
        $theme = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Bamcom Office">'.
            '<a:themeElements><a:clrScheme name="Bamcom">'.
            '<a:dk1><a:srgbClr val="0F172A"/></a:dk1><a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>'.
            '<a:dk2><a:srgbClr val="334155"/></a:dk2><a:lt2><a:srgbClr val="F8FAFC"/></a:lt2>'.
            '<a:accent1><a:srgbClr val="4F46E5"/></a:accent1><a:accent2><a:srgbClr val="10B981"/></a:accent2>'.
            '<a:accent3><a:srgbClr val="F59E0B"/></a:accent3><a:accent4><a:srgbClr val="EF4444"/></a:accent4>'.
            '<a:accent5><a:srgbClr val="8B5CF6"/></a:accent5><a:accent6><a:srgbClr val="06B6D4"/></a:accent6>'.
            '<a:hlink><a:srgbClr val="4F46E5"/></a:hlink><a:folHlink><a:srgbClr val="6366F1"/></a:folHlink>'.
            '</a:clrScheme>'.
            '<a:fontScheme name="Bamcom"><a:majorFont><a:latin typeface="Segoe UI"/></a:majorFont><a:minorFont><a:latin typeface="Segoe UI"/></a:minorFont></a:fontScheme>'.
            '<a:fmtScheme name="Bamcom"><a:fillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:fillStyleLst><a:lnStyleLst><a:ln><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln></a:lnStyleLst><a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst><a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst></a:fmtScheme>'.
            '</a:themeElements></a:theme>';
        $zip->addFromString('ppt/theme/theme1.xml', $theme);

        // 6. SlideMaster
        $slideMaster = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld>'.
            '<p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>'.
            '<p:sldLayoutIdLst><p:sldLayoutId id="2147483649" r:id="rId1"/></p:sldLayoutIdLst></p:sldMaster>';
        $zip->addFromString('ppt/slideMasters/slideMaster1.xml', $slideMaster);

        $slideMasterRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'.
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>'.
            '</Relationships>';
        $zip->addFromString('ppt/slideMasters/_rels/slideMaster1.xml.rels', $slideMasterRels);

        // 7. SlideLayout
        $slideLayout = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/></p:spTree></p:cSld></p:sldLayout>';
        $zip->addFromString('ppt/slideLayouts/slideLayout1.xml', $slideLayout);

        $slideLayoutRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>'.
            '</Relationships>';
        $zip->addFromString('ppt/slideLayouts/_rels/slideLayout1.xml.rels', $slideLayoutRels);

        // 8. Individual Slides
        $slideRelsTemplate = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>'.
            '</Relationships>';

        foreach ($slides as $i => $slideXml) {
            $slideNum = $i + 1;
            $zip->addFromString('ppt/slides/slide'.$slideNum.'.xml', $slideXml);
            $zip->addFromString('ppt/slides/_rels/slide'.$slideNum.'.xml.rels', $slideRelsTemplate);
        }

        $zip->close();
        $binary = file_get_contents($tempFile);
        unlink($tempFile);

        return new Response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    protected function createTitleSlide(string $periodLabel, string $dateFrom, string $dateTo): string
    {
        $meta = "Reporting Period: {$periodLabel} ({$dateFrom} to {$dateTo}) | Generated: ".now()->format('F j, Y');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            // Background card
            $this->shape(457200, 457200, 8229600, 4229100, 'EEF2FF', '4F46E5', 20000).
            // Title text box
            $this->textBox(914400, 1143000, 7315200, 2000000, [
                ['text' => 'BAMCOM REAL ESTATE CRM', 'size' => 3800, 'bold' => true, 'color' => '4F46E5'],
                ['text' => 'Executive Analytics & Performance Briefing', 'size' => 2000, 'bold' => false, 'color' => '1E293B'],
                ['text' => '', 'size' => 1000, 'bold' => false, 'color' => '1E293B'],
                ['text' => $meta, 'size' => 1300, 'bold' => false, 'color' => '64748B'],
            ]).
            '</p:spTree></p:cSld></p:sld>';
    }

    /**
     * @param  array<string, mixed>  $kpis
     */
    protected function createKpiSlide(array $kpis, string $periodLabel): string
    {
        $items = [
            ['title' => 'NEW LEADS', 'val' => (string) ($kpis['new_leads']['value'] ?? 0), 'sub' => ($kpis['new_leads']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'HOT LEADS', 'val' => (string) ($kpis['hot_leads']['value'] ?? 0), 'sub' => ($kpis['hot_leads']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'ACTIVE CHATS', 'val' => (string) ($kpis['active_conversations']['value'] ?? 0), 'sub' => ($kpis['active_conversations']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'INSPECTIONS', 'val' => (string) ($kpis['inspections']['value'] ?? 0), 'sub' => ($kpis['inspections']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'OPEN DEALS', 'val' => (string) ($kpis['open_deals']['value'] ?? 0), 'sub' => ($kpis['open_deals']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'PIPELINE VAL', 'val' => 'N'.number_format($kpis['pipeline_value']['value'] ?? 0, 0), 'sub' => ($kpis['pipeline_value']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'SALES WON', 'val' => 'N'.number_format($kpis['sales_won']['value'] ?? 0, 0), 'sub' => ($kpis['sales_won']['change_percent'] ?? 0).'% vs prev'],
            ['title' => 'CONVERSION', 'val' => ($kpis['conversion_rate']['formatted'] ?? '0.0%'), 'sub' => 'Won Deals Rate'],
        ];

        $shapes = '';
        $cardW = 1850000;
        $cardH = 1100000;
        $startX = 500000;
        $startY = 1200000;
        $gapX = 200000;
        $gapY = 300000;

        foreach ($items as $idx => $item) {
            $col = $idx % 4;
            $row = (int) ($idx / 4);
            $x = $startX + ($col * ($cardW + $gapX));
            $y = $startY + ($row * ($cardH + $gapY));

            $shapes .= $this->shape($x, $y, $cardW, $cardH, 'F8FAFC', 'CBD5E1', 10000);
            $shapes .= $this->textBox($x + 100000, $y + 80000, $cardW - 200000, $cardH - 160000, [
                ['text' => $item['title'], 'size' => 1100, 'bold' => true, 'color' => '64748B'],
                ['text' => $item['val'], 'size' => 1800, 'bold' => true, 'color' => '0F172A'],
                ['text' => $item['sub'], 'size' => 1000, 'bold' => false, 'color' => '10B981'],
            ]);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            $this->slideHeader('Executive KPI Summary Scorecard', "Direct database calculations for {$periodLabel}").
            $shapes.
            '</p:spTree></p:cSld></p:sld>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $stages
     */
    protected function createFunnelSlide(array $stages): string
    {
        $lines = [];
        foreach (array_slice($stages, 0, 7) as $stg) {
            $name = $stg['name'] ?? '';
            $leads = $stg['leads_count'] ?? 0;
            $deals = $stg['deals_count'] ?? 0;
            $val = 'N'.number_format($stg['total_value'] ?? 0, 0);
            $rate = ($stg['conversion_rate'] ?? 0).'%';
            $lines[] = ['text' => "• {$name}: {$leads} leads | {$deals} deals | {$val} volume ({$rate} conversion)", 'size' => 1300, 'bold' => false, 'color' => '1E293B'];
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            $this->slideHeader('Pipeline Funnel Progression & Velocity', 'Stage-by-stage progression from initial inquiry to closed won').
            $this->shape(500000, 1200000, 8144000, 3400000, 'F8FAFC', 'E2E8F0', 10000).
            $this->textBox(700000, 1400000, 7744000, 3000000, $lines).
            '</p:spTree></p:cSld></p:sld>';
    }

    /**
     * @param  array<string, mixed>  $sp
     */
    protected function createSalesSlide(array $sp): string
    {
        $wonRev = 'N'.number_format($sp['won_revenue'] ?? 0, 2);
        $lostRev = 'N'.number_format($sp['lost_revenue'] ?? 0, 2);
        $avgSize = 'N'.number_format($sp['avg_deal_size'] ?? 0, 2);
        $winRate = ($sp['win_rate'] ?? 0).'%';

        $lines = [
            ['text' => 'Sales Performance & Velocity Breakdown:', 'size' => 1500, 'bold' => true, 'color' => '4F46E5'],
            ['text' => '', 'size' => 800, 'bold' => false, 'color' => '000000'],
            ['text' => '• Total Deals Created / Tracked: '.($sp['total_deals'] ?? 0), 'size' => 1300, 'bold' => false, 'color' => '1E293B'],
            ['text' => '• Closed Won Deals: '.($sp['won_deals'] ?? 0)." ({$wonRev} closed revenue)", 'size' => 1300, 'bold' => false, 'color' => '1E293B'],
            ['text' => '• Closed Lost Deals: '.($sp['lost_deals'] ?? 0)." ({$lostRev} missed revenue)", 'size' => 1300, 'bold' => false, 'color' => '1E293B'],
            ['text' => '• Open Pipeline Deals: '.($sp['open_deals'] ?? 0), 'size' => 1300, 'bold' => false, 'color' => '1E293B'],
            ['text' => "• Overall Win Rate: {$winRate}", 'size' => 1400, 'bold' => true, 'color' => '10B981'],
            ['text' => "• Average Won Deal Size: {$avgSize}", 'size' => 1300, 'bold' => false, 'color' => '1E293B'],
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            $this->slideHeader('Sales Performance & Win Rate Analytics', 'Deal velocity, closed volume, and revenue attribution').
            $this->shape(500000, 1200000, 8144000, 3400000, 'F8FAFC', 'E2E8F0', 10000).
            $this->textBox(700000, 1400000, 7744000, 3000000, $lines).
            '</p:spTree></p:cSld></p:sld>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $agents
     */
    protected function createAgentSlide(array $agents): string
    {
        $lines = [
            ['text' => 'Sales Representative Quotas & Performance:', 'size' => 1400, 'bold' => true, 'color' => '4F46E5'],
            ['text' => '', 'size' => 600, 'bold' => false, 'color' => '000000'],
        ];

        foreach (array_slice($agents, 0, 6) as $ag) {
            $name = $ag['name'] ?? 'Agent';
            $leads = $ag['assigned_leads'] ?? 0;
            $inspections = $ag['completed_inspections'] ?? 0;
            $won = $ag['won_deals'] ?? 0;
            $wonRev = 'N'.number_format($ag['won_revenue'] ?? 0, 0);
            $rate = ($ag['win_rate'] ?? 0).'%';
            $lines[] = ['text' => "• {$name}: {$leads} leads | {$inspections} inspections | {$won} won deals | {$wonRev} revenue ({$rate} win rate)", 'size' => 1200, 'bold' => false, 'color' => '1E293B'];
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            $this->slideHeader('Sales Representative Performance Leaderboard', 'Individual agent quotas, property inspections, and deal closures').
            $this->shape(500000, 1200000, 8144000, 3400000, 'F8FAFC', 'E2E8F0', 10000).
            $this->textBox(700000, 1400000, 7744000, 3000000, $lines).
            '</p:spTree></p:cSld></p:sld>';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, array<string, mixed>>  $campaigns
     */
    protected function createMarketingSlide(array $sources, array $campaigns): string
    {
        $lines = [
            ['text' => 'Lead Acquisition Sources:', 'size' => 1300, 'bold' => true, 'color' => '4F46E5'],
        ];

        foreach (array_slice($sources, 0, 4) as $s) {
            $label = $s['label'] ?? $s['source'] ?? 'Unknown';
            $count = $s['count'] ?? 0;
            $share = ($s['percentage'] ?? 0).'%';
            $won = 'N'.number_format($s['won_revenue'] ?? 0, 0);
            $lines[] = ['text' => "  - {$label}: {$count} leads ({$share} share) -> {$won} won revenue", 'size' => 1100, 'bold' => false, 'color' => '1E293B'];
        }

        $lines[] = ['text' => '', 'size' => 600, 'bold' => false, 'color' => '000000'];
        $lines[] = ['text' => 'WhatsApp Broadcast Campaigns:', 'size' => 1300, 'bold' => true, 'color' => '4F46E5'];

        foreach (array_slice($campaigns, 0, 3) as $c) {
            $name = $c['name'] ?? 'Campaign';
            $rec = $c['total_recipients'] ?? 0;
            $del = $c['delivered_count'] ?? 0;
            $read = $c['read_count'] ?? 0;
            $lines[] = ['text' => "  - {$name}: {$rec} recipients | {$del} delivered | {$read} read", 'size' => 1100, 'bold' => false, 'color' => '1E293B'];
        }
        if (empty($campaigns)) {
            $lines[] = ['text' => '  - No active broadcast campaigns in this time period.', 'size' => 1100, 'bold' => false, 'color' => '64748B'];
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            $this->slideHeader('Marketing & WhatsApp Broadcast Performance', 'Channel attribution and broadcast campaign reach').
            $this->shape(500000, 1200000, 8144000, 3400000, 'F8FAFC', 'E2E8F0', 10000).
            $this->textBox(700000, 1400000, 7744000, 3000000, $lines).
            '</p:spTree></p:cSld></p:sld>';
    }

    /**
     * @param  array<string, mixed>  $ai
     * @param  array<int, array<string, mixed>>  $handovers
     */
    protected function createAiSlide(array $ai, array $handovers): string
    {
        $tokens = number_format($ai['total_tokens'] ?? 0);
        $latency = ($ai['avg_duration_ms'] ?? 0).' ms';
        $resRate = ($ai['ai_resolution_rate'] ?? 0).'%';

        $lines = [
            ['text' => 'AI Engine Execution & Autonomous Resolution:', 'size' => 1300, 'bold' => true, 'color' => '4F46E5'],
            ['text' => '• Total Conversations: '.($ai['total_conversations'] ?? 0).' (AI: '.($ai['ai_conversations'] ?? 0).', Hybrid: '.($ai['hybrid_conversations'] ?? 0).', Human: '.($ai['human_conversations'] ?? 0).')', 'size' => 1100, 'bold' => false, 'color' => '1E293B'],
            ['text' => '• Total LLM Invocations: '.($ai['total_ai_executions'] ?? 0)." ({$tokens} tokens consumed)", 'size' => 1100, 'bold' => false, 'color' => '1E293B'],
            ['text' => "• Average Inference Latency: {$latency}", 'size' => 1100, 'bold' => false, 'color' => '1E293B'],
            ['text' => "• Autonomous AI Resolution Rate: {$resRate}", 'size' => 1300, 'bold' => true, 'color' => '10B981'],
            ['text' => '', 'size' => 600, 'bold' => false, 'color' => '000000'],
            ['text' => 'Human Escalation & Handover Triggers:', 'size' => 1300, 'bold' => true, 'color' => '4F46E5'],
        ];

        foreach (array_slice($handovers, 0, 4) as $h) {
            $trig = $h['trigger'] ?? 'Customer Request';
            $cnt = $h['count'] ?? 0;
            $pct = ($h['percentage'] ?? 0).'%';
            $lines[] = ['text' => "  - {$trig}: {$cnt} escalations ({$pct})", 'size' => 1100, 'bold' => false, 'color' => '1E293B'];
        }
        if (empty($handovers)) {
            $lines[] = ['text' => '  - No human escalation events in this period.', 'size' => 1100, 'bold' => false, 'color' => '64748B'];
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n".
            '<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'.
            '<p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr/>'.
            $this->slideHeader('AI Autonomous Automation & Human Escalations', 'Gemini AI agent resolution efficiency and escalation reasons').
            $this->shape(500000, 1200000, 8144000, 3400000, 'F8FAFC', 'E2E8F0', 10000).
            $this->textBox(700000, 1400000, 7744000, 3000000, $lines).
            '</p:spTree></p:cSld></p:sld>';
    }

    protected function slideHeader(string $title, string $subtitle): string
    {
        return $this->shape(500000, 300000, 8144000, 750000, '4F46E5', '4338CA', 0).
            $this->textBox(700000, 380000, 7744000, 600000, [
                ['text' => $title, 'size' => 2000, 'bold' => true, 'color' => 'FFFFFF'],
                ['text' => $subtitle, 'size' => 1100, 'bold' => false, 'color' => 'E0E7FF'],
            ]);
    }

    protected function shape(int $x, int $y, int $cx, int $cy, string $fillHex, string $strokeHex, int $lineWidth): string
    {
        return '<p:sp>'.
            '<p:nvSpPr><p:cNvPr id="'.rand(100, 99999).'" name="Box"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>'.
            '<p:spPr><a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm>'.
            '<a:prstGeom prst="roundRect"><a:avLst><a:gd name="adj" fmla="val 12000"/></a:avLst></a:prstGeom>'.
            '<a:solidFill><a:srgbClr val="'.$fillHex.'"/></a:solidFill>'.
            ($strokeHex ? '<a:ln w="'.$lineWidth.'"><a:solidFill><a:srgbClr val="'.$strokeHex.'"/></a:solidFill></a:ln>' : '').
            '</p:spPr></p:sp>';
    }

    /**
     * @param  array<int, array{text: string, size: int, bold: bool, color: string}>  $paragraphs
     */
    protected function textBox(int $x, int $y, int $cx, int $cy, array $paragraphs): string
    {
        $body = '';
        foreach ($paragraphs as $p) {
            $cleanText = htmlspecialchars($p['text']);
            $boldAttr = $p['bold'] ? ' b="1"' : '';
            $body .= '<a:p><a:r><a:rPr lang="en-US" sz="'.$p['size'].'"'.$boldAttr.'><a:solidFill><a:srgbClr val="'.$p['color'].'"/></a:solidFill></a:rPr>'.
                '<a:t>'.$cleanText.'</a:t></a:r></a:p>';
        }

        return '<p:sp>'.
            '<p:nvSpPr><p:cNvPr id="'.rand(100, 99999).'" name="Text"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr/></p:nvSpPr>'.
            '<p:spPr><a:xfrm><a:off x="'.$x.'" y="'.$y.'"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm></p:spPr>'.
            '<p:txBody><a:bodyPr/><a:lstStyle/>'.$body.'</p:txBody></p:sp>';
    }
}
