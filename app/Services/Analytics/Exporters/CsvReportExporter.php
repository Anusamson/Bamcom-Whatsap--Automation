<?php

namespace App\Services\Analytics\Exporters;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvReportExporter
{
    /**
     * Generate and stream a comprehensive CSV export.
     *
     * @param  array<string, mixed>  $data
     */
    public function export(array $data): StreamedResponse
    {
        $period = $data['dateRange']['period'] ?? '30d';
        $timestamp = now()->format('Ymd_His');
        $filename = "bamcom_analytics_report_{$period}_{$timestamp}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($data): void {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // --- Header & Report Metadata ---
            fputcsv($handle, ['BAMCOM REAL ESTATE CRM - EXECUTIVE ANALYTICS REPORT']);
            fputcsv($handle, ['Reporting Period', $data['dateRange']['label'] ?? 'Custom']);
            fputcsv($handle, ['Date Range', ($data['dateRange']['date_from'] ?? '').' to '.($data['dateRange']['date_to'] ?? '')]);
            fputcsv($handle, ['Generated At', now()->toDateTimeString()]);
            fputcsv($handle, []);

            // --- Section 1: Executive KPIs ---
            fputcsv($handle, ['=== EXECUTIVE KEY PERFORMANCE INDICATORS ===']);
            fputcsv($handle, ['Metric', 'Current Period Value', 'Previous Period Value', 'Period-over-Period Change']);

            $kpis = $data['kpis'] ?? [];
            if (! empty($kpis)) {
                fputcsv($handle, ['New Leads', $kpis['new_leads']['value'] ?? 0, $kpis['new_leads']['previous'] ?? 0, ($kpis['new_leads']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Hot Leads', $kpis['hot_leads']['value'] ?? 0, $kpis['hot_leads']['previous'] ?? 0, ($kpis['hot_leads']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Active Conversations', $kpis['active_conversations']['value'] ?? 0, $kpis['active_conversations']['previous'] ?? 0, ($kpis['active_conversations']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Inspections', $kpis['inspections']['value'] ?? 0, $kpis['inspections']['previous'] ?? 0, ($kpis['inspections']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Open Deals', $kpis['open_deals']['value'] ?? 0, $kpis['open_deals']['previous'] ?? 0, ($kpis['open_deals']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Pipeline Value (NGN)', number_format($kpis['pipeline_value']['value'] ?? 0, 2), number_format($kpis['pipeline_value']['previous'] ?? 0, 2), ($kpis['pipeline_value']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Sales Won Revenue (NGN)', number_format($kpis['sales_won']['value'] ?? 0, 2), number_format($kpis['sales_won']['previous'] ?? 0, 2), ($kpis['sales_won']['change_percent'] ?? 0).'%']);
                fputcsv($handle, ['Conversion Rate', ($kpis['conversion_rate']['value'] ?? 0).'%', 'N/A', 'N/A']);
            }
            fputcsv($handle, []);

            // --- Section 2: Lead Source Breakdown ---
            fputcsv($handle, ['=== LEAD SOURCE PERFORMANCE & ATTRIBUTION ===']);
            fputcsv($handle, ['Acquisition Channel', 'Lead Count', 'Volume Share (%)', 'Attributed Won Revenue (NGN)']);
            $sources = $data['reports']['lead_source']['sources'] ?? [];
            foreach ($sources as $s) {
                fputcsv($handle, [
                    $s['label'] ?? $s['source'] ?? 'Unknown',
                    $s['count'] ?? 0,
                    ($s['percentage'] ?? 0).'%',
                    number_format($s['won_revenue'] ?? 0, 2),
                ]);
            }
            fputcsv($handle, []);

            // --- Section 3: Pipeline Funnel Progression ---
            fputcsv($handle, ['=== PIPELINE FUNNEL PROGRESSION ===']);
            fputcsv($handle, ['Stage Order', 'Stage Name', 'Leads Count', 'Deals Count', 'Total Deal Value (NGN)', 'Funnel Conversion Rate (%)']);
            $stages = $data['reports']['pipeline_funnel']['stages'] ?? [];
            foreach ($stages as $stg) {
                fputcsv($handle, [
                    $stg['order'] ?? 1,
                    $stg['name'] ?? '',
                    $stg['leads_count'] ?? 0,
                    $stg['deals_count'] ?? 0,
                    number_format($stg['total_value'] ?? 0, 2),
                    ($stg['conversion_rate'] ?? 0).'%',
                ]);
            }
            fputcsv($handle, []);

            // --- Section 4: Sales Performance ---
            fputcsv($handle, ['=== SALES PERFORMANCE & DEAL VELOCITY ===']);
            fputcsv($handle, ['Metric', 'Value']);
            $sp = $data['reports']['sales_performance'] ?? [];
            fputcsv($handle, ['Total Deals', $sp['total_deals'] ?? 0]);
            fputcsv($handle, ['Won Deals', $sp['won_deals'] ?? 0]);
            fputcsv($handle, ['Lost Deals', $sp['lost_deals'] ?? 0]);
            fputcsv($handle, ['Open Deals', $sp['open_deals'] ?? 0]);
            fputcsv($handle, ['Won Revenue (NGN)', number_format($sp['won_revenue'] ?? 0, 2)]);
            fputcsv($handle, ['Lost Revenue (NGN)', number_format($sp['lost_revenue'] ?? 0, 2)]);
            fputcsv($handle, ['Win Rate (%)', ($sp['win_rate'] ?? 0).'%']);
            fputcsv($handle, ['Average Won Deal Size (NGN)', number_format($sp['avg_deal_size'] ?? 0, 2)]);
            fputcsv($handle, []);

            // --- Section 5: Agent Performance Leaderboard ---
            fputcsv($handle, ['=== SALES AGENT PERFORMANCE LEADERBOARD ===']);
            fputcsv($handle, ['Agent Name', 'Email', 'Assigned Leads', 'Completed Inspections', 'Open Deals', 'Won Deals', 'Won Revenue (NGN)', 'Win Rate (%)']);
            $agents = $data['reports']['agent_performance']['agents'] ?? [];
            foreach ($agents as $agent) {
                fputcsv($handle, [
                    $agent['name'] ?? '',
                    $agent['email'] ?? '',
                    $agent['assigned_leads'] ?? 0,
                    $agent['completed_inspections'] ?? 0,
                    $agent['open_deals'] ?? 0,
                    $agent['won_deals'] ?? 0,
                    number_format($agent['won_revenue'] ?? 0, 2),
                    ($agent['win_rate'] ?? 0).'%',
                ]);
            }
            fputcsv($handle, []);

            // --- Section 6: Inspection Conversion ---
            fputcsv($handle, ['=== INSPECTION OUTCOMES & CONVERSIONS ===']);
            fputcsv($handle, ['Status / Metric', 'Count / Percentage']);
            $insp = $data['reports']['inspection_conversion'] ?? [];
            fputcsv($handle, ['Total Scheduled Inspections', $insp['total'] ?? 0]);
            fputcsv($handle, ['Completed Inspections', $insp['completed'] ?? 0]);
            fputcsv($handle, ['Requested', $insp['requested'] ?? 0]);
            fputcsv($handle, ['Scheduled', $insp['scheduled'] ?? 0]);
            fputcsv($handle, ['Confirmed', $insp['confirmed'] ?? 0]);
            fputcsv($handle, ['Cancelled', $insp['cancelled'] ?? 0]);
            fputcsv($handle, ['No Show', $insp['no_show'] ?? 0]);
            fputcsv($handle, ['Completion Rate (%)', ($insp['completion_rate'] ?? 0).'%']);
            fputcsv($handle, ['Won Deals Attributed to Inspections', $insp['won_deals_from_inspection'] ?? 0]);
            fputcsv($handle, ['Inspection-to-Won Deal Rate (%)', ($insp['inspection_to_deal_rate'] ?? 0).'%']);
            fputcsv($handle, []);

            // --- Section 7: WhatsApp Campaign Performance ---
            fputcsv($handle, ['=== WHATSAPP CAMPAIGN PERFORMANCE ===']);
            fputcsv($handle, ['Campaign Name', 'Status', 'Total Recipients', 'Sent', 'Delivered', 'Read', 'Failed', 'Delivery Rate (%)', 'Read Rate (%)']);
            $campaigns = $data['reports']['campaign_performance']['campaigns'] ?? [];
            foreach ($campaigns as $camp) {
                $sent = max(1, $camp['sent_count'] ?? 0);
                $delivered = $camp['delivered_count'] ?? 0;
                $delRate = round(($delivered / $sent) * 100, 1);
                $readRate = $delivered > 0 ? round((($camp['read_count'] ?? 0) / $delivered) * 100, 1) : 0;
                fputcsv($handle, [
                    $camp['name'] ?? '',
                    $camp['status'] ?? '',
                    $camp['total_recipients'] ?? 0,
                    $camp['sent_count'] ?? 0,
                    $delivered,
                    $camp['read_count'] ?? 0,
                    $camp['failed_count'] ?? 0,
                    $delRate.'%',
                    $readRate.'%',
                ]);
            }
            fputcsv($handle, []);

            // --- Section 8: AI Conversations & Autonomous Resolution ---
            fputcsv($handle, ['=== AI CONVERSATIONS & AUTOMATION METRICS ===']);
            fputcsv($handle, ['Metric', 'Value']);
            $ai = $data['reports']['ai_conversations'] ?? [];
            fputcsv($handle, ['Total Conversations', $ai['total_conversations'] ?? 0]);
            fputcsv($handle, ['AI Handled Conversations', $ai['ai_conversations'] ?? 0]);
            fputcsv($handle, ['Hybrid Conversations', $ai['hybrid_conversations'] ?? 0]);
            fputcsv($handle, ['Human Only Conversations', $ai['human_conversations'] ?? 0]);
            fputcsv($handle, ['Total AI Engine Invocations', $ai['total_ai_executions'] ?? 0]);
            fputcsv($handle, ['Total Tokens Consumed', number_format($ai['total_tokens'] ?? 0)]);
            fputcsv($handle, ['Average Execution Latency (ms)', ($ai['avg_duration_ms'] ?? 0).' ms']);
            fputcsv($handle, ['Autonomous AI Resolution Rate (%)', ($ai['ai_resolution_rate'] ?? 0).'%']);
            fputcsv($handle, []);

            // --- Section 9: Human Escalations & Handovers ---
            fputcsv($handle, ['=== HUMAN HANDOVERS & ESCALATIONS ===']);
            fputcsv($handle, ['Escalation Trigger / Reason', 'Count', 'Share (%)']);
            $handovers = $data['reports']['human_handovers']['triggers'] ?? [];
            foreach ($handovers as $h) {
                fputcsv($handle, [
                    $h['trigger'] ?? 'Customer Request',
                    $h['count'] ?? 0,
                    ($h['percentage'] ?? 0).'%',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
