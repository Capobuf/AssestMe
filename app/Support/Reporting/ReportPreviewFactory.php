<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\FindingPagePlanData;
use App\Data\Reports\ReportFindingData;
use App\Data\Reports\ReportPriorityData;
use App\Data\Reports\ReportRiskMatrixData;
use App\Data\Reports\ReportSolutionData;

final class ReportPreviewFactory
{
    /** @param array<string, bool|int|string|null> $settings */
    public function make(array $settings): AssessmentReportData
    {
        $settings += [
            'primary_color' => '#65A30D',
            'branding' => 'consultant',
            'cover_title_mode' => 'separate',
            'cover' => true,
            'executive_summary' => true,
            'summary_table' => true,
            'risk_legend' => true,
            'show_priority_descriptions' => true,
            'costs' => true,
            'alternative_solutions' => true,
            'technical_notes' => true,
            'show_resolution' => true,
            'evidence' => true,
            'evidence_captions' => true,
            'page_numbers' => true,
            'content_index' => false,
            'methodology' => false,
            'signature_block' => false,
            'disclaimer' => false,
            'application_name' => 'AssestMe',
        ];

        $priorities = [
            new ReportPriorityData('low', 'Bassa', 'Monitorare e migliorare', '#15803D', 1),
            new ReportPriorityData('moderate', 'Moderata', 'Pianificare nel ciclo ordinario', '#D97706', 2),
            new ReportPriorityData('high', 'Alta', 'Intervenire a breve termine', '#B42318', 3),
        ];
        $solutions = [
            new ReportSolutionData(1, 'Backup immutabile fuori dominio', 'Attivare una copia indipendente, cifrata e non raggiungibile con le credenziali ordinarie.', null, 'Medio', null, 'requires_quote', null, null, null, 'one_off', null, null, 'Richiede preventivo', true, false, 1),
            new ReportSolutionData(2, 'Repository offline a rotazione', 'Aggiungere supporti offline custoditi separatamente e verificati ogni mese.', null, 'Alto', null, 'requires_quote', null, null, null, 'one_off', null, null, 'Richiede preventivo', false, true, 2),
        ];
        $matrix = new ReportRiskMatrixData(
            consequences: [
                ['id' => 1, 'label' => 'Limitata', 'color' => '#15803D'],
                ['id' => 2, 'label' => 'Seria', 'color' => '#B42318'],
            ],
            likelihoods: [
                ['id' => 1, 'label' => 'Possibile', 'color' => '#D97706'],
                ['id' => 2, 'label' => 'Probabile', 'color' => '#B42318'],
            ],
            cells: [
                ['consequence_id' => 1, 'likelihood_id' => 1, 'priority_label' => 'Bassa', 'priority_color' => '#15803D', 'current' => false],
                ['consequence_id' => 1, 'likelihood_id' => 2, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 2, 'likelihood_id' => 1, 'priority_label' => 'Alta', 'priority_color' => '#B42318', 'current' => false],
                ['consequence_id' => 2, 'likelihood_id' => 2, 'priority_label' => 'Alta', 'priority_color' => '#B42318', 'current' => true],
            ],
            currentConsequenceId: 2,
            currentLikelihoodId: 2,
            resultingPriorityLabel: 'Alta',
            resultingPriorityColor: '#B42318',
        );
        $finding = new ReportFindingData(
            id: 1,
            number: 1,
            includeInReport: true,
            title: 'Copie di sicurezza non isolate e ripristino non provato',
            category: 'Continuità operativa',
            scopeType: 'organization',
            scopeLabel: 'Intera azienda',
            scopeDescription: null,
            sites: ['Sede principale'],
            assets: [],
            consequenceLabel: 'Seria',
            consequenceColor: '#B42318',
            likelihoodLabel: 'Probabile',
            likelihoodColor: '#B42318',
            priorityLabel: 'Alta',
            priorityColor: '#B42318',
            priorityOverridden: false,
            priorityRationale: null,
            problem: 'Le copie restano raggiungibili con le credenziali ordinarie e manca una prova recente di ripristino.',
            entrepreneurNotes: 'Un guasto o un attacco potrebbe rendere indisponibili contemporaneamente dati e copie di sicurezza.',
            technicalNotes: 'Definire RPO, RTO e responsabilità del test.',
            status: 'planned',
            statusLabel: 'Pianificato',
            solutions: $solutions,
            evidences: [],
            resolutionNotes: null,
            resolvedAt: null,
            resolvedAtLabel: null,
            pagePlan: new FindingPagePlanData([1, 2], [], false),
            riskMatrix: $matrix,
        );

        $titlePattern = trim((string) ($settings['default_title_pattern'] ?? 'Assessment IT — {client}'));
        $title = trim(str_replace('{client}', '', $titlePattern), " \t\n\r\0\x0B—-");

        return new AssessmentReportData(
            generatedAt: '2026-07-24T10:00:00+00:00',
            applicationVersion: 'preview',
            locale: 'it',
            title: $title === '' ? 'Assessment IT' : $title,
            assessmentId: 1,
            assessmentTitle: 'Assessment infrastruttura e continuità',
            assessmentDate: '2026-07-24',
            assessmentDateLabel: '24/07/2026',
            assessmentStatus: 'draft',
            assessmentStatusLabel: 'Bozza',
            assessmentScope: 'Intera azienda',
            scopeDescription: null,
            assessmentSites: ['Sede principale'],
            introduction: 'Valutazione deterministica dimostrativa delle principali aree infrastrutturali.',
            executiveSummary: 'Sono prioritari l’isolamento delle copie e la verifica periodica del ripristino.',
            methodologyNotes: null,
            clientId: 1,
            clientName: 'Azienda Demo S.r.l.',
            clientLegalName: 'Azienda Demo S.r.l.',
            clientVatNumber: 'IT01234567890',
            clientTaxCode: null,
            clientEmail: null,
            clientPhone: null,
            clientWebsite: null,
            clientAddress: 'Via Esempio 10, Treviso',
            logos: [],
            priorityLegend: $priorities,
            findings: [$finding],
            settingsSnapshot: $settings,
        );
    }
}
