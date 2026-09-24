<?php

namespace App\Services\Analytics\Exporters;

use Symfony\Component\HttpFoundation\Response;

class DocReportExporter
{
    /**
     * Generate and return a Microsoft Word (.doc) document.
     *
     * @param  array<string, mixed>  $data
     */
    public function export(array $data): Response
    {
        $period = $data['dateRange']['period'] ?? '30d';
        $periodLabel = htmlspecialchars($data['dateRange']['label'] ?? 'Custom');
        $dateFrom = htmlspecialchars($data['dateRange']['date_from'] ?? '');
        $dateTo = htmlspecialchars($data['dateRange']['date_to'] ?? '');
        $generatedAt = now()->format('F j, Y - H:i:s');
        $timestamp = now()->format('Ymd_His');
        $filename = "bamcom_analytics_report_{$period}_{$timestamp}.doc";

        $kpis = $data['kpis'] ?? [];
        $leadSources = $data['reports']['lead_source']['sources'] ?? [];
        $stages = $data['reports']['pipeline_funnel']['stages'] ?? [];
        $sp = $data['reports']['sales_performance'] ?? [];
        $agents = $data['reports']['agent_performance']['agents'] ?? [];
        $insp = $data['reports']['inspection_conversion'] ?? [];
        $campaigns = $data['reports']['campaign_performance']['campaigns'] ?? [];
        $ai = $data['reports']['ai_conversations'] ?? [];
        $handovers = $data['reports']['human_handovers']['triggers'] ?? [];

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">';
        $html .= '<head><meta charset="utf-8"><title>BAMCOM CRM Executive Analytics Report</title>';
        $html .= '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom><w:DoNotOptimizeForBrowser/></w:WordDocument></xml><![endif]-->';
        $html .= '<style>
            @page Section1 { size: 8.5in 11.0in; margin: 0.8in 0.8in 0.8in 0.8in; mso-header-margin: .5in; mso-footer-margin: .5in; }
            div.Section1 { page: Section1; font-family: "Segoe UI", Arial, sans-serif; color: #1e293b; font-size: 11pt; line-height: 1.4; }
            h1 { color: #4338ca; font-size: 20pt; margin: 0 0 4px 0; font-weight: bold; }
            h2 { color: #1e293b; font-size: 14pt; margin: 18px 0 8px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
            p { margin: 4px 0; }
            .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 9pt; font-weight: bold; }
            .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .kpi-table td { width: 25%; padding: 10px; border: 1px solid #cbd5e1; background-color: #f8fafc; vertical-align: top; }
            .kpi-title { font-size: 9pt; color: #64748b; text-transform: uppercase; font-weight: bold; }
            .kpi-val { font-size: 16pt; font-weight: bold; color: #0f172a; margin-top: 4px; }
            .kpi-sub { font-size: 9pt; color: #10b981; font-weight: 600; margin-top: 2px; }
            .data-table { width: 100%; border-collapse: collapse; margin: 10px 0 20px 0; font-size: 10pt; }
            .data-table th { background-color: #4f46e5; color: #ffffff; padding: 8px 10px; text-align: left; font-weight: bold; border: 1px solid #4338ca; }
            .data-table td { padding: 8px 10px; border: 1px solid #e2e8f0; }
            .data-table tr:nth-child(even) { background-color: #f8fafc; }
            .header-banner { background-color: #eef2ff; border-left: 6px solid #4f46e5; padding: 12px 16px; margin-bottom: 20px; }
        </style></head><body>';
        $html .= '<div class="Section1">';

        // Executive Banner
        $html .= '<div class="header-banner">';
        $html .= '<h1>BAMCOM REAL ESTATE CRM</h1>';
        $html .= '<p style="font-size: 13pt; color: #4338ca; font-weight: 600; margin: 0 0 6px 0;">Executive Analytics &amp; Performance Briefing</p>';
        $html .= '<p style="color: #64748b; font-size: 9.5pt;"><b>Reporting Window:</b> '.$periodLabel.' ('.$dateFrom.' to '.$dateTo.') | <b>Generated:</b> '.$generatedAt.'</p>';
        $html .= '</div>';

        // KPI Scorecard Grid
        $html .= '<h2>1. Executive KPI Summary</h2>';
        $html .= '<table class="kpi-table">';
        $html .= '<tr>';
        $html .= '<td><div class="kpi-title">New Leads</div><div class="kpi-val">'.($kpis['new_leads']['value'] ?? 0).'</div><div class="kpi-sub">'.($kpis['new_leads']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '<td><div class="kpi-title">Hot Leads</div><div class="kpi-val">'.($kpis['hot_leads']['value'] ?? 0).'</div><div class="kpi-sub">'.($kpis['hot_leads']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '<td><div class="kpi-title">Active Conversations</div><div class="kpi-val">'.($kpis['active_conversations']['value'] ?? 0).'</div><div class="kpi-sub">'.($kpis['active_conversations']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '<td><div class="kpi-title">Inspections</div><div class="kpi-val">'.($kpis['inspections']['value'] ?? 0).'</div><div class="kpi-sub">'.($kpis['inspections']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '</tr><tr>';
        $html .= '<td><div class="kpi-title">Open Deals</div><div class="kpi-val">'.($kpis['open_deals']['value'] ?? 0).'</div><div class="kpi-sub">'.($kpis['open_deals']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '<td><div class="kpi-title">Pipeline Value</div><div class="kpi-val">'.($kpis['pipeline_value']['formatted'] ?? '₦0.00').'</div><div class="kpi-sub">'.($kpis['pipeline_value']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '<td><div class="kpi-title">Sales Won Revenue</div><div class="kpi-val">'.($kpis['sales_won']['formatted'] ?? '₦0.00').'</div><div class="kpi-sub">'.($kpis['sales_won']['change_percent'] ?? 0).'% vs prev</div></td>';
        $html .= '<td><div class="kpi-title">Deal Win Rate</div><div class="kpi-val">'.($kpis['conversion_rate']['formatted'] ?? '0.0%').'</div><div class="kpi-sub">Closed Conversion</div></td>';
        $html .= '</tr>';
        $html .= '</table>';

        // Pipeline Funnel
        $html .= '<h2>2. Pipeline Funnel Velocity &amp; Drop-Offs</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Order</th><th>Stage</th><th>Leads Count</th><th>Deals Count</th><th>Total Value</th><th>Conversion Rate</th></tr></thead><tbody>';
        foreach ($stages as $stage) {
            $html .= '<tr><td>'.($stage['order'] ?? 1).'</td><td><b>'.htmlspecialchars($stage['name'] ?? '').'</b></td><td>'.($stage['leads_count'] ?? 0).'</td><td>'.($stage['deals_count'] ?? 0).'</td><td>'.($stage['formatted_value'] ?? '₦0.00').'</td><td>'.($stage['conversion_rate'] ?? 0).'%</td></tr>';
        }
        $html .= '</tbody></table>';

        // Lead Source Attribution
        $html .= '<h2>3. Lead Sources &amp; Revenue Attribution</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Channel</th><th>Lead Count</th><th>Volume Share</th><th>Won Revenue</th></tr></thead><tbody>';
        foreach ($leadSources as $src) {
            $html .= '<tr><td><b>'.htmlspecialchars($src['label'] ?? '').'</b></td><td>'.($src['count'] ?? 0).'</td><td>'.($src['percentage'] ?? 0).'%</td><td>'.($src['formatted_won_revenue'] ?? '₦0.00').'</td></tr>';
        }
        $html .= '</tbody></table>';

        // Sales Performance
        $html .= '<h2>4. Sales Velocity &amp; Win Rate</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Total Deals</th><th>Won Deals</th><th>Lost Deals</th><th>Won Revenue</th><th>Lost Revenue</th><th>Win Rate</th><th>Avg Won Deal Size</th></tr></thead><tbody>';
        $html .= '<tr><td>'.($sp['total_deals'] ?? 0).'</td><td>'.($sp['won_deals'] ?? 0).'</td><td>'.($sp['lost_deals'] ?? 0).'</td><td>'.($sp['formatted_won_revenue'] ?? '₦0.00').'</td><td>'.($sp['formatted_lost_revenue'] ?? '₦0.00').'</td><td>'.($sp['win_rate'] ?? 0).'%</td><td>'.($sp['formatted_avg_deal_size'] ?? '₦0.00').'</td></tr>';
        $html .= '</tbody></table>';

        // Agent Leaderboard
        $html .= '<h2>5. Sales Representative Leaderboard</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Representative</th><th>Email</th><th>Assigned Leads</th><th>Inspections</th><th>Open Deals</th><th>Won Deals</th><th>Won Revenue</th><th>Win Rate</th></tr></thead><tbody>';
        foreach ($agents as $ag) {
            $html .= '<tr><td><b>'.htmlspecialchars($ag['name'] ?? '').'</b></td><td>'.htmlspecialchars($ag['email'] ?? '').'</td><td>'.($ag['assigned_leads'] ?? 0).'</td><td>'.($ag['completed_inspections'] ?? 0).'</td><td>'.($ag['open_deals'] ?? 0).'</td><td>'.($ag['won_deals'] ?? 0).'</td><td>'.($ag['formatted_won_revenue'] ?? '₦0.00').'</td><td>'.($ag['win_rate'] ?? 0).'%</td></tr>';
        }
        $html .= '</tbody></table>';

        // Inspection Outcomes
        $html .= '<h2>6. Property Inspection Outcomes</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Total Scheduled</th><th>Completed</th><th>Scheduled</th><th>Cancelled</th><th>No Show</th><th>Completion Rate</th><th>Won Deals</th><th>Conversion Rate</th></tr></thead><tbody>';
        $html .= '<tr><td>'.($insp['total'] ?? 0).'</td><td>'.($insp['completed'] ?? 0).'</td><td>'.($insp['scheduled'] ?? 0).'</td><td>'.($insp['cancelled'] ?? 0).'</td><td>'.($insp['no_show'] ?? 0).'</td><td>'.($insp['completion_rate'] ?? 0).'%</td><td>'.($insp['won_deals_from_inspection'] ?? 0).'</td><td>'.($insp['inspection_to_deal_rate'] ?? 0).'%</td></tr>';
        $html .= '</tbody></table>';

        // WhatsApp Broadcast Campaign Performance
        $html .= '<h2>7. WhatsApp Broadcast Campaigns</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Campaign Name</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Failed</th><th>Delivery Rate</th><th>Read Rate</th></tr></thead><tbody>';
        foreach ($campaigns as $c) {
            $sent = max(1, $c['sent_count'] ?? 0);
            $del = $c['delivered_count'] ?? 0;
            $delPct = round(($del / $sent) * 100, 1);
            $readPct = $del > 0 ? round((($c['read_count'] ?? 0) / $del) * 100, 1) : 0;
            $html .= '<tr><td><b>'.htmlspecialchars($c['name'] ?? '').'</b></td><td>'.htmlspecialchars($c['status'] ?? '').'</td><td>'.($c['total_recipients'] ?? 0).'</td><td>'.($c['sent_count'] ?? 0).'</td><td>'.$del.'</td><td>'.($c['read_count'] ?? 0).'</td><td>'.($c['failed_count'] ?? 0).'</td><td>'.$delPct.'%</td><td>'.$readPct.'%</td></tr>';
        }
        $html .= '</tbody></table>';

        // AI Conversations & Escalations
        $html .= '<h2>8. AI Automation &amp; Human Escalations</h2>';
        $html .= '<table class="data-table"><thead><tr><th>Total Conversations</th><th>AI Handled</th><th>Hybrid</th><th>Human Only</th><th>AI Invocations</th><th>Total Tokens</th><th>Avg Latency</th><th>AI Resolution Rate</th></tr></thead><tbody>';
        $html .= '<tr><td>'.($ai['total_conversations'] ?? 0).'</td><td>'.($ai['ai_conversations'] ?? 0).'</td><td>'.($ai['hybrid_conversations'] ?? 0).'</td><td>'.($ai['human_conversations'] ?? 0).'</td><td>'.($ai['total_ai_executions'] ?? 0).'</td><td>'.number_format($ai['total_tokens'] ?? 0).'</td><td>'.($ai['avg_duration_ms'] ?? 0).' ms</td><td>'.($ai['ai_resolution_rate'] ?? 0).'%</td></tr>';
        $html .= '</tbody></table>';

        if (! empty($handovers)) {
            $html .= '<h3>Top Escalation Triggers</h3>';
            $html .= '<table class="data-table" style="width: 60%;"><thead><tr><th>Trigger Reason</th><th>Count</th><th>Share (%)</th></tr></thead><tbody>';
            foreach ($handovers as $h) {
                $html .= '<tr><td>'.htmlspecialchars($h['trigger'] ?? '').'</td><td>'.($h['count'] ?? 0).'</td><td>'.($h['percentage'] ?? 0).'%</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '</div></body></html>';

        return new Response($html, 200, [
            'Content-Type' => 'application/msword; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
