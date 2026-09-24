<?php

namespace App\Services\Analytics\Exporters;

use Symfony\Component\HttpFoundation\Response;

class PdfReportExporter
{
    protected array $pages = [];

    protected string $currentPageContent = '';

    protected float $pageWidth = 595.28; // Standard A4 points

    protected float $pageHeight = 841.89;

    protected float $currentY = 40;

    /**
     * Export complete executive CRM analytics report to PDF format.
     *
     * @param  array<string, mixed>  $data
     */
    public function export(array $data): Response
    {
        $this->pages = [];
        $this->currentPageContent = '';
        $this->currentY = 40;

        $period = $data['dateRange']['period'] ?? '30d';
        $periodLabel = $data['dateRange']['label'] ?? 'Custom';
        $dateFrom = $data['dateRange']['date_from'] ?? '';
        $dateTo = $data['dateRange']['date_to'] ?? '';
        $timestamp = now()->format('Ymd_His');
        $filename = "bamcom_analytics_report_{$period}_{$timestamp}.pdf";

        $kpis = $data['kpis'] ?? [];
        $sources = $data['reports']['lead_source']['sources'] ?? [];
        $stages = $data['reports']['pipeline_funnel']['stages'] ?? [];
        $sp = $data['reports']['sales_performance'] ?? [];
        $agents = $data['reports']['agent_performance']['agents'] ?? [];
        $insp = $data['reports']['inspection_conversion'] ?? [];
        $campaigns = $data['reports']['campaign_performance']['campaigns'] ?? [];
        $ai = $data['reports']['ai_conversations'] ?? [];
        $handovers = $data['reports']['human_handovers']['triggers'] ?? [];

        // ================= PAGE 1 =================
        $this->startNewPage();
        $this->renderHeaderBanner($periodLabel, $dateFrom, $dateTo);

        // Section 1: Executive KPIs Scorecard
        $this->renderSectionTitle('1. Executive KPI Summary Scorecard');
        $this->renderKpiGrid($kpis);

        // Section 2: Pipeline Funnel Progression
        $this->renderSectionTitle('2. Pipeline Funnel Progression & Velocity');
        $funnelHeaders = ['Stage', 'Order', 'Leads', 'Deals', 'Total Value (NGN)', 'Conversion'];
        $funnelRows = [];
        foreach (array_slice($stages, 0, 7) as $stg) {
            $funnelRows[] = [
                (string) ($stg['name'] ?? ''),
                (string) ($stg['order'] ?? 1),
                (string) ($stg['leads_count'] ?? 0),
                (string) ($stg['deals_count'] ?? 0),
                'N'.number_format($stg['total_value'] ?? 0, 2),
                ($stg['conversion_rate'] ?? 0).'%',
            ];
        }
        $this->renderTable($funnelHeaders, $funnelRows, [130, 45, 55, 55, 140, 70]);

        // Section 3: Lead Sources
        $this->renderSectionTitle('3. Lead Sources & Revenue Attribution');
        $sourceHeaders = ['Acquisition Source', 'Leads Count', 'Share (%)', 'Attributed Revenue (NGN)'];
        $sourceRows = [];
        foreach (array_slice($sources, 0, 5) as $s) {
            $sourceRows[] = [
                (string) ($s['label'] ?? $s['source'] ?? 'Unknown'),
                (string) ($s['count'] ?? 0),
                ($s['percentage'] ?? 0).'%',
                'N'.number_format($s['won_revenue'] ?? 0, 2),
            ];
        }
        $this->renderTable($sourceHeaders, $sourceRows, [160, 95, 85, 155]);

        // ================= PAGE 2 =================
        $this->startNewPage();
        $this->renderPageHeader();

        // Section 4: Sales Performance
        $this->renderSectionTitle('4. Sales Performance & Deal Velocity');
        $salesHeaders = ['Metric', 'Value'];
        $salesRows = [
            ['Total Deals Created / Closed', (string) ($sp['total_deals'] ?? 0)],
            ['Won Deals Volume', (string) ($sp['won_deals'] ?? 0)],
            ['Lost Deals Volume', (string) ($sp['lost_deals'] ?? 0)],
            ['Open Pipeline Deals', (string) ($sp['open_deals'] ?? 0)],
            ['Total Sales Won Revenue', 'N'.number_format($sp['won_revenue'] ?? 0, 2)],
            ['Total Lost Deal Revenue', 'N'.number_format($sp['lost_revenue'] ?? 0, 2)],
            ['Overall Deal Win Rate', ($sp['win_rate'] ?? 0).'%'],
            ['Average Closed Won Deal Size', 'N'.number_format($sp['avg_deal_size'] ?? 0, 2)],
        ];
        $this->renderTable($salesHeaders, $salesRows, [240, 255]);

        // Section 5: Agent Performance Leaderboard
        $this->renderSectionTitle('5. Sales Representative Leaderboard');
        $agentHeaders = ['Sales Agent', 'Assigned Leads', 'Inspections', 'Deals Won', 'Won Revenue (NGN)', 'Win Rate'];
        $agentRows = [];
        foreach (array_slice($agents, 0, 6) as $ag) {
            $agentRows[] = [
                (string) ($ag['name'] ?? ''),
                (string) ($ag['assigned_leads'] ?? 0),
                (string) ($ag['completed_inspections'] ?? 0),
                (string) ($ag['won_deals'] ?? 0),
                'N'.number_format($ag['won_revenue'] ?? 0, 2),
                ($ag['win_rate'] ?? 0).'%',
            ];
        }
        $this->renderTable($agentHeaders, $agentRows, [135, 75, 75, 60, 105, 45]);

        // Section 6: Inspection Conversion
        $this->renderSectionTitle('6. Property Inspection Conversion');
        $inspHeaders = ['Inspection Metric', 'Count / Rate'];
        $inspRows = [
            ['Total Scheduled Inspections', (string) ($insp['total'] ?? 0)],
            ['Successfully Completed Inspections', (string) ($insp['completed'] ?? 0)],
            ['Inspection Completion Rate (%)', ($insp['completion_rate'] ?? 0).'%'],
            ['Closed Won Deals From Completed Inspections', (string) ($insp['won_deals_from_inspection'] ?? 0)],
            ['Inspection-to-Deal Conversion Rate (%)', ($insp['inspection_to_deal_rate'] ?? 0).'%'],
        ];
        $this->renderTable($inspHeaders, $inspRows, [240, 255]);

        // ================= PAGE 3 =================
        $this->startNewPage();
        $this->renderPageHeader();

        // Section 7: WhatsApp Campaign Performance
        $this->renderSectionTitle('7. WhatsApp Broadcast Campaign Performance');
        $campHeaders = ['Campaign Name', 'Status', 'Recipients', 'Delivered', 'Read', 'Delivery Rate'];
        $campRows = [];
        foreach (array_slice($campaigns, 0, 6) as $c) {
            $sent = max(1, $c['sent_count'] ?? 0);
            $del = $c['delivered_count'] ?? 0;
            $delRate = round(($del / $sent) * 100, 1);
            $campRows[] = [
                (string) ($c['name'] ?? ''),
                (string) ($c['status'] ?? ''),
                (string) ($c['total_recipients'] ?? 0),
                (string) $del,
                (string) ($c['read_count'] ?? 0),
                $delRate.'%',
            ];
        }
        if (empty($campRows)) {
            $campRows[] = ['No campaigns executed in this period', '-', '-', '-', '-', '-'];
        }
        $this->renderTable($campHeaders, $campRows, [160, 65, 65, 70, 65, 70]);

        // Section 8: AI Automation & Escalation
        $this->renderSectionTitle('8. AI Autonomous Conversations & Escalations');
        $aiHeaders = ['AI & Escalation Metric', 'Value'];
        $aiRows = [
            ['Total Conversations in Period', (string) ($ai['total_conversations'] ?? 0)],
            ['AI Autonomous Conversations', (string) ($ai['ai_conversations'] ?? 0)],
            ['Hybrid Mode Conversations', (string) ($ai['hybrid_conversations'] ?? 0)],
            ['Human Only Conversations', (string) ($ai['human_conversations'] ?? 0)],
            ['Total AI Execution Log Runs', (string) ($ai['total_ai_executions'] ?? 0)],
            ['Total Gemini / LLM Tokens Consumed', number_format($ai['total_tokens'] ?? 0)],
            ['Average AI Processing Latency', ($ai['avg_duration_ms'] ?? 0).' ms'],
            ['Autonomous AI Resolution Rate', ($ai['ai_resolution_rate'] ?? 0).'%'],
        ];
        $this->renderTable($aiHeaders, $aiRows, [240, 255]);

        // Top Escalation Triggers
        if (! empty($handovers)) {
            $this->renderSectionTitle('9. Top Human Escalation Triggers');
            $handoverHeaders = ['Trigger Reason', 'Volume', 'Share (%)'];
            $handoverRows = [];
            foreach (array_slice($handovers, 0, 5) as $h) {
                $handoverRows[] = [
                    (string) ($h['trigger'] ?? 'Customer Request'),
                    (string) ($h['count'] ?? 0),
                    ($h['percentage'] ?? 0).'%',
                ];
            }
            $this->renderTable($handoverHeaders, $handoverRows, [240, 120, 135]);
        }

        // Render page footers with page numbers
        $totalPages = count($this->pages) + ($this->currentPageContent !== '' ? 1 : 0);
        $this->finalizePages($totalPages);

        $pdfBinary = $this->buildPdfString();

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    protected function startNewPage(): void
    {
        if ($this->currentPageContent !== '') {
            $this->pages[] = $this->currentPageContent;
        }
        $this->currentPageContent = '';
        $this->currentY = 35;
    }

    protected function renderHeaderBanner(string $periodLabel, string $dateFrom, string $dateTo): void
    {
        $this->rect(35, $this->currentY, 525.28, 65, '#4f46e5');
        $this->text('BAMCOM REAL ESTATE CRM', 50, $this->currentY + 22, 16, true, '#ffffff');
        $this->text('Executive Analytics & Performance Report', 50, $this->currentY + 38, 10, false, '#e0e7ff');
        $meta = "Period: {$periodLabel} ({$dateFrom} to {$dateTo}) | Generated: ".now()->format('Y-m-d H:i');
        $this->text($meta, 50, $this->currentY + 52, 8.5, false, '#c7d2fe');
        $this->currentY += 80;
    }

    protected function renderPageHeader(): void
    {
        $this->rect(35, $this->currentY, 525.28, 25, '#f8fafc', '#e2e8f0', 0.5);
        $this->text('BAMCOM REAL ESTATE CRM - EXECUTIVE REPORT', 45, $this->currentY + 16, 8.5, true, '#4f46e5');
        $this->text('Confidential Internal Report', 400, $this->currentY + 16, 8, false, '#64748b');
        $this->currentY += 35;
    }

    protected function renderSectionTitle(string $title): void
    {
        $this->rect(35, $this->currentY, 525.28, 18, '#eef2ff');
        $this->text($title, 42, $this->currentY + 13, 9.5, true, '#3730a3');
        $this->currentY += 24;
    }

    /**
     * @param  array<string, mixed>  $kpis
     */
    protected function renderKpiGrid(array $kpis): void
    {
        $items = [
            ['label' => 'NEW LEADS', 'val' => (string) ($kpis['new_leads']['value'] ?? 0), 'sub' => ($kpis['new_leads']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'HOT LEADS', 'val' => (string) ($kpis['hot_leads']['value'] ?? 0), 'sub' => ($kpis['hot_leads']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'ACTIVE CHATS', 'val' => (string) ($kpis['active_conversations']['value'] ?? 0), 'sub' => ($kpis['active_conversations']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'INSPECTIONS', 'val' => (string) ($kpis['inspections']['value'] ?? 0), 'sub' => ($kpis['inspections']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'OPEN DEALS', 'val' => (string) ($kpis['open_deals']['value'] ?? 0), 'sub' => ($kpis['open_deals']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'PIPELINE VAL', 'val' => 'N'.number_format($kpis['pipeline_value']['value'] ?? 0, 0), 'sub' => ($kpis['pipeline_value']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'SALES WON', 'val' => 'N'.number_format($kpis['sales_won']['value'] ?? 0, 0), 'sub' => ($kpis['sales_won']['change_percent'] ?? 0).'% vs prev'],
            ['label' => 'CONVERSION', 'val' => ($kpis['conversion_rate']['formatted'] ?? '0.0%'), 'sub' => 'Won Deals Rate'],
        ];

        $boxW = 125.0;
        $boxH = 42.0;
        $gap = 8.4;
        $startX = 35.0;

        foreach ($items as $idx => $item) {
            $row = (int) ($idx / 4);
            $col = $idx % 4;
            $x = $startX + ($col * ($boxW + $gap));
            $y = $this->currentY + ($row * ($boxH + 6));

            $this->rect($x, $y, $boxW, $boxH, '#f8fafc', '#cbd5e1', 0.5);
            $this->text($item['label'], $x + 6, $y + 12, 7, true, '#64748b');
            $this->text($item['val'], $x + 6, $y + 26, 11, true, '#0f172a');
            $this->text($item['sub'], $x + 6, $y + 36, 6.5, false, '#10b981');
        }

        $this->currentY += 100;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, float>  $widths
     */
    protected function renderTable(array $headers, array $rows, array $widths): void
    {
        $startX = 35.0;
        $rowH = 16.0;

        // Render header row
        $this->rect($startX, $this->currentY, array_sum($widths), $rowH, '#4f46e5');
        $curX = $startX;
        foreach ($headers as $i => $h) {
            $w = $widths[$i] ?? 80;
            $this->text($h, $curX + 4, $this->currentY + 11, 7.5, true, '#ffffff');
            $curX += $w;
        }
        $this->currentY += $rowH;

        // Render data rows
        foreach ($rows as $rIdx => $row) {
            $bgColor = ($rIdx % 2 === 0) ? '#ffffff' : '#f8fafc';
            $this->rect($startX, $this->currentY, array_sum($widths), $rowH, $bgColor, '#e2e8f0', 0.3);

            $curX = $startX;
            foreach ($row as $cIdx => $cell) {
                $w = $widths[$cIdx] ?? 80;
                $this->text((string) $cell, $curX + 4, $this->currentY + 11, 7.5, false, '#1e293b');
                $curX += $w;
            }
            $this->currentY += $rowH;
        }

        $this->currentY += 14;
    }

    protected function finalizePages(int $totalPages): void
    {
        if ($this->currentPageContent !== '') {
            $this->pages[] = $this->currentPageContent;
        }
        $this->currentPageContent = '';

        foreach ($this->pages as $idx => &$pageContent) {
            $pageNum = $idx + 1;
            $footerText = "Page {$pageNum} of {$totalPages}   |   BAMCOM Real Estate AI CRM - Confidential";
            $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $footerText);

            $footerOps = sprintf(
                "BT 0.45 0.45 0.45 rg /F1 7.5 Tf 1 0 0 1 180.00 20.00 Tm (%s) Tj ET\n",
                $escaped
            );
            $pageContent .= $footerOps;
        }
    }

    protected function rect(float $x, float $y, float $w, float $h, string $fillColor = '', string $strokeColor = '', float $lineWidth = 1): void
    {
        $pdfY = $this->pageHeight - $y - $h;
        $ops = '';
        if ($strokeColor !== '') {
            $rgb = $this->hexToRgb($strokeColor);
            $ops .= sprintf('%.3F %.3F %.3F RG %.2F w ', $rgb[0], $rgb[1], $rgb[2], $lineWidth);
        }
        if ($fillColor !== '') {
            $rgb = $this->hexToRgb($fillColor);
            $ops .= sprintf('%.3F %.3F %.3F rg ', $rgb[0], $rgb[1], $rgb[2]);
        }
        $ops .= sprintf('%.2F %.2F %.2F %.2F re ', $x, $pdfY, $w, $h);
        if ($fillColor !== '' && $strokeColor !== '') {
            $ops .= "B\n";
        } elseif ($fillColor !== '') {
            $ops .= "f\n";
        } elseif ($strokeColor !== '') {
            $ops .= "S\n";
        }
        $this->currentPageContent .= $ops;
    }

    protected function text(string $text, float $x, float $y, float $size = 10, bool $bold = false, string $color = '#1e293b'): void
    {
        $font = $bold ? '/F2' : '/F1';
        $pdfY = $this->pageHeight - $y;
        $rgb = $this->hexToRgb($color);
        // Clean characters for standard WinAnsi / Latin1
        $clean = str_replace(['₦', "\r", "\n", "\t"], ['N', ' ', ' ', ' '], $text);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $clean);

        $this->currentPageContent .= sprintf(
            "BT %.3F %.3F %.3F rg %s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $rgb[0], $rgb[1], $rgb[2],
            $font, $size,
            $x, $pdfY,
            $escaped
        );
    }

    protected function buildPdfString(): string
    {
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        $objId = 1;

        // 1: Catalog
        $offsets[$objId] = strlen($out);
        $out .= "{$objId} 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objId++;

        // Reserve 2 for Pages
        $pagesObjId = 2;
        $objId++;

        // Fonts
        $fontF1Id = $objId++;
        $offsets[$fontF1Id] = strlen($out);
        $out .= "{$fontF1Id} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        $fontF2Id = $objId++;
        $offsets[$fontF2Id] = strlen($out);
        $out .= "{$fontF2Id} 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $pageObjIds = [];
        foreach ($this->pages as $pageStream) {
            $streamLen = strlen($pageStream);
            $streamObjId = $objId++;
            $offsets[$streamObjId] = strlen($out);
            $out .= "{$streamObjId} 0 obj\n<< /Length {$streamLen} >>\nstream\n{$pageStream}endstream\nendobj\n";

            $pageObjId = $objId++;
            $offsets[$pageObjId] = strlen($out);
            $out .= "{$pageObjId} 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageWidth} {$this->pageHeight}] /Resources << /Font << /F1 {$fontF1Id} 0 R /F2 {$fontF2Id} 0 R >> >> /Contents {$streamObjId} 0 R >>\nendobj\n";
            $pageObjIds[] = $pageObjId;
        }

        // Pages object (ID 2)
        $kids = implode(' ', array_map(fn ($id) => "{$id} 0 R", $pageObjIds));
        $pageCount = count($pageObjIds);
        $pagesObjContent = "{$pagesObjId} 0 obj\n<< /Type /Pages /Kids [{$kids}] /Count {$pageCount} >>\nendobj\n";
        $offsets[$pagesObjId] = strlen($out);
        $out .= $pagesObjContent;

        // XRef Table
        $xrefOffset = strlen($out);
        $totalObjs = $objId;
        $out .= "xref\n0 {$totalObjs}\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i < $totalObjs; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $out .= "trailer\n<< /Size {$totalObjs} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $out;
    }

    protected function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255.0,
            hexdec(substr($hex, 2, 2)) / 255.0,
            hexdec(substr($hex, 4, 2)) / 255.0,
        ];
    }
}
