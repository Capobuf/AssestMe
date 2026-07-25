<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Actions\Reports\FormatEstimate;
use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\FindingPagePlanData;
use App\Data\Reports\ReportAssetData;
use App\Data\Reports\ReportEvidenceData;
use App\Data\Reports\ReportFindingData;
use App\Data\Reports\ReportPriorityData;
use App\Data\Reports\ReportRiskMatrixData;
use App\Data\Reports\ReportSolutionData;
use App\Enums\BillingFrequency;
use App\Enums\CoverTitleMode;
use App\Enums\EstimateType;
use App\Services\Reporting\BuildReportLogos;
use App\Services\Reporting\ResolveReportTitle;

final class ReportPreviewFactory
{
    public function __construct(
        private readonly FormatEstimate $formatEstimate,
        private readonly BuildReportLogos $buildReportLogos,
        private readonly ResolveReportTitle $resolveReportTitle,
    ) {}

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
            'currency' => 'EUR',
            'currency_symbol' => '€',
            'currency_symbol_position' => 'after',
            'currency_decimals' => 2,
        ];

        $priorities = [
            new ReportPriorityData('low', 'Bassa', 'Monitorare e migliorare', '#15803D', 1),
            new ReportPriorityData('moderate', 'Moderata', 'Pianificare nel ciclo ordinario', '#D97706', 2),
            new ReportPriorityData('high', 'Alta', 'Intervenire a breve termine', '#B42318', 3),
            new ReportPriorityData('critical', 'Critica', 'Intervenire immediatamente', '#7F1D1D', 4),
        ];

        // These DTOs mirror the base template library without querying or mutating persisted templates.
        $findings = [
            $this->backupFinding($this->riskMatrix(), $settings),
            $this->remoteAccessFinding($settings),
            $this->nasNotificationFinding($settings),
        ];

        $coverTitleMode = CoverTitleMode::tryFrom((string) $settings['cover_title_mode'])
            ?? CoverTitleMode::Separate;
        $clientName = 'Azienda Demo S.r.l.';
        $title = ($this->resolveReportTitle)(
            null,
            (string) ($settings['default_title_pattern'] ?? 'Assessment IT — {client}'),
            $clientName,
            $coverTitleMode,
        );
        $consultantLogoPath = $settings['consultant_logo_path'] ?? null;
        $consultantLogoPath = is_string($consultantLogoPath) && $consultantLogoPath !== ''
            ? $consultantLogoPath
            : null;

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
            assessmentSites: ['Sede principale', 'Filiale operativa'],
            introduction: 'Valutazione dimostrativa costruita con contenuti rappresentativi della libreria dei template.',
            executiveSummary: 'Le priorità riguardano il ripristino dei backup, la protezione dell’accesso remoto e il monitoraggio tempestivo degli apparati.',
            methodologyNotes: null,
            clientId: 1,
            clientName: $clientName,
            clientLegalName: 'Azienda Demo S.r.l.',
            clientVatNumber: 'IT01234567890',
            clientTaxCode: null,
            clientEmail: null,
            clientPhone: null,
            clientWebsite: null,
            clientAddress: 'Via Esempio 10, Treviso',
            logos: ($this->buildReportLogos)(
                (string) $settings['branding'],
                $consultantLogoPath,
                null,
            ),
            priorityLegend: $priorities,
            findings: $findings,
            settingsSnapshot: $settings,
        );
    }

    private function riskMatrix(): ReportRiskMatrixData
    {
        return new ReportRiskMatrixData(
            consequences: [
                ['id' => 1, 'label' => 'Limitata', 'color' => '#15803D'],
                ['id' => 2, 'label' => 'Significativa', 'color' => '#D97706'],
                ['id' => 3, 'label' => 'Seria', 'color' => '#B42318'],
                ['id' => 4, 'label' => 'Critica', 'color' => '#7F1D1D'],
            ],
            likelihoods: [
                ['id' => 1, 'label' => 'Rara', 'color' => '#15803D'],
                ['id' => 2, 'label' => 'Improbabile', 'color' => '#65A30D'],
                ['id' => 3, 'label' => 'Possibile', 'color' => '#D97706'],
                ['id' => 4, 'label' => 'Probabile', 'color' => '#B42318'],
            ],
            cells: [
                ['consequence_id' => 1, 'likelihood_id' => 1, 'priority_label' => 'Bassa', 'priority_color' => '#15803D', 'current' => false],
                ['consequence_id' => 1, 'likelihood_id' => 2, 'priority_label' => 'Bassa', 'priority_color' => '#15803D', 'current' => false],
                ['consequence_id' => 1, 'likelihood_id' => 3, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 1, 'likelihood_id' => 4, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 2, 'likelihood_id' => 1, 'priority_label' => 'Bassa', 'priority_color' => '#15803D', 'current' => false],
                ['consequence_id' => 2, 'likelihood_id' => 2, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 2, 'likelihood_id' => 3, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 2, 'likelihood_id' => 4, 'priority_label' => 'Alta', 'priority_color' => '#B42318', 'current' => false],
                ['consequence_id' => 3, 'likelihood_id' => 1, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 3, 'likelihood_id' => 2, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 3, 'likelihood_id' => 3, 'priority_label' => 'Alta', 'priority_color' => '#B42318', 'current' => true],
                ['consequence_id' => 3, 'likelihood_id' => 4, 'priority_label' => 'Critica', 'priority_color' => '#7F1D1D', 'current' => false],
                ['consequence_id' => 4, 'likelihood_id' => 1, 'priority_label' => 'Moderata', 'priority_color' => '#D97706', 'current' => false],
                ['consequence_id' => 4, 'likelihood_id' => 2, 'priority_label' => 'Alta', 'priority_color' => '#B42318', 'current' => false],
                ['consequence_id' => 4, 'likelihood_id' => 3, 'priority_label' => 'Critica', 'priority_color' => '#7F1D1D', 'current' => false],
                ['consequence_id' => 4, 'likelihood_id' => 4, 'priority_label' => 'Critica', 'priority_color' => '#7F1D1D', 'current' => false],
            ],
            currentConsequenceId: 3,
            currentLikelihoodId: 3,
            resultingPriorityLabel: 'Alta',
            resultingPriorityColor: '#B42318',
        );
    }

    /** @param array<string, bool|int|string|null> $settings */
    private function backupFinding(ReportRiskMatrixData $matrix, array $settings): ReportFindingData
    {
        return new ReportFindingData(
            id: 1,
            number: 1,
            includeInReport: true,
            title: 'Ripristino dei backup non verificato',
            category: 'Backup',
            scopeType: 'organization',
            scopeLabel: 'Intera azienda',
            scopeDescription: 'Sistemi e dati coperti dalle procedure di backup aziendali.',
            sites: ['Sede principale', 'Filiale operativa'],
            assets: [],
            consequenceLabel: 'Seria',
            consequenceColor: '#B42318',
            likelihoodLabel: 'Possibile',
            likelihoodColor: '#D97706',
            priorityLabel: 'Alta',
            priorityColor: '#B42318',
            priorityOverridden: false,
            priorityRationale: null,
            problem: 'Non risultano prove recenti e documentate di ripristino dei dati dai backup disponibili.',
            entrepreneurNotes: 'Un backup può risultare completato ma essere inutilizzabile quando serve. Solo una prova di ripristino consente di verificare dati, credenziali e procedure.',
            technicalNotes: 'Verificare campione di dati, tempi di ripristino, integrità, cifratura, credenziali e registrazione dell’esito.',
            status: 'in_progress',
            statusLabel: 'In corso',
            solutions: [
                new ReportSolutionData(
                    id: 1,
                    title: 'Eseguire e documentare una prova di ripristino completa',
                    description: 'Definire un campione rappresentativo, eseguire il ripristino in un ambiente sicuro e documentare risultato, tempi e problemi.',
                    comparisonNotes: 'È l’opzione raccomandata perché produce una verifica completa e ripetibile.',
                    effortLabel: 'Alto',
                    effortNotes: 'Coinvolge più sistemi e richiede una finestra concordata.',
                    estimateType: 'range',
                    amountMin: '1800',
                    amountMax: '2600',
                    currencyCode: 'EUR',
                    billingFrequency: 'one_off',
                    customBillingFrequency: null,
                    estimateNotes: 'Intervallo comprensivo di preparazione, esecuzione e verbale.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::Range,
                        '1800',
                        '2600',
                        'EUR',
                        BillingFrequency::OneOff,
                    ),
                    recommended: true,
                    implemented: false,
                    sortOrder: 1,
                ),
                new ReportSolutionData(
                    id: 2,
                    title: 'Servizio gestito di test periodico',
                    description: 'Programmare un ripristino campione annuale con verifica dell’integrità e aggiornamento della procedura operativa.',
                    comparisonNotes: 'Riduce l’impegno interno, ma introduce un costo ricorrente.',
                    effortLabel: 'Medio',
                    effortNotes: 'Richiede coordinamento annuale e accesso controllato.',
                    estimateType: 'exact',
                    amountMin: '390',
                    amountMax: null,
                    currencyCode: 'EUR',
                    billingFrequency: 'yearly',
                    customBillingFrequency: null,
                    estimateNotes: 'Canone per un test pianificato ogni anno.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::Exact,
                        '390',
                        null,
                        'EUR',
                        BillingFrequency::Yearly,
                    ),
                    recommended: false,
                    implemented: true,
                    sortOrder: 2,
                ),
                new ReportSolutionData(
                    id: 3,
                    title: 'Ambiente dedicato di disaster recovery',
                    description: 'Realizzare un ambiente separato per prove complete e ripartenza controllata dei servizi prioritari.',
                    comparisonNotes: 'Offre la copertura più ampia, con maggiore investimento e progettazione.',
                    effortLabel: 'Molto alto',
                    effortNotes: 'Richiede progettazione infrastrutturale e test applicativi.',
                    estimateType: 'requires_quote',
                    amountMin: null,
                    amountMax: null,
                    currencyCode: null,
                    billingFrequency: 'one_off',
                    customBillingFrequency: null,
                    estimateNotes: 'Sono necessari analisi tecnica e preventivo infrastrutturale.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::RequiresQuote,
                        null,
                        null,
                        null,
                        BillingFrequency::OneOff,
                    ),
                    recommended: false,
                    implemented: false,
                    sortOrder: 3,
                ),
            ],
            evidences: [],
            resolutionNotes: null,
            resolvedAt: null,
            resolvedAtLabel: null,
            pagePlan: $settings['alternative_solutions'] === true
                ? new FindingPagePlanData([1, 2], [3], true)
                : new FindingPagePlanData([2], [1], true),
            riskMatrix: $matrix,
        );
    }

    /** @param array<string, bool|int|string|null> $settings */
    private function remoteAccessFinding(array $settings): ReportFindingData
    {
        return new ReportFindingData(
            id: 2,
            number: 2,
            includeInReport: true,
            title: 'Servizio RDP esposto direttamente su Internet',
            category: 'Sicurezza',
            scopeType: 'selected_assets',
            scopeLabel: 'Asset selezionati',
            scopeDescription: null,
            sites: ['Sede principale'],
            assets: [
                new ReportAssetData(1, 'Firewall perimetrale', 'Firewall', 'Sede principale', 'Vendor Demo', 'FW-100', 'fw-demo', '192.0.2.1', 'Firewall perimetrale'),
                new ReportAssetData(2, 'Server gestionale', 'Server', 'Sede principale', null, null, 'srv-gestionale', '192.0.2.20', 'Server gestionale'),
            ],
            consequenceLabel: 'Critica',
            consequenceColor: '#7F1D1D',
            likelihoodLabel: 'Probabile',
            likelihoodColor: '#B42318',
            priorityLabel: 'Critica',
            priorityColor: '#7F1D1D',
            priorityOverridden: false,
            priorityRationale: null,
            problem: 'Il servizio Remote Desktop è raggiungibile direttamente da Internet senza un livello di accesso remoto protetto intermedio.',
            entrepreneurNotes: 'L’esposizione diretta aumenta la possibilità di tentativi automatizzati, furto di credenziali e compromissione dei sistemi.',
            technicalNotes: 'Verificare NAT, regole firewall, autenticazione, MFA, log e dipendenze prima della modifica.',
            status: 'planned',
            statusLabel: 'Pianificato',
            solutions: [
                new ReportSolutionData(
                    id: 4,
                    title: 'Rimuovere l’esposizione diretta e utilizzare una VPN',
                    description: 'Chiudere la pubblicazione diretta e consentire RDP esclusivamente attraverso una VPN protetta con autenticazione multifattore.',
                    comparisonNotes: null,
                    effortLabel: 'Medio',
                    effortNotes: 'Richiede verifica di utenti, dispositivi e modalità di accesso remoto.',
                    estimateType: 'requires_analysis',
                    amountMin: null,
                    amountMax: null,
                    currencyCode: null,
                    billingFrequency: 'one_off',
                    customBillingFrequency: null,
                    estimateNotes: 'La stima dipende dal firewall e dal numero di utenti.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::RequiresAnalysis,
                        null,
                        null,
                        null,
                        BillingFrequency::OneOff,
                    ),
                    recommended: true,
                    implemented: false,
                    sortOrder: 1,
                ),
                new ReportSolutionData(
                    id: 5,
                    title: 'Gateway gestito con MFA',
                    description: 'Adottare un servizio di accesso remoto gestito con identità centralizzate, MFA e registrazione degli accessi.',
                    comparisonNotes: 'Riduce la gestione locale ma introduce un canone per utente.',
                    effortLabel: 'Basso',
                    effortNotes: 'Attivazione e distribuzione del client agli utenti autorizzati.',
                    estimateType: 'exact',
                    amountMin: '29',
                    amountMax: null,
                    currencyCode: 'EUR',
                    billingFrequency: 'monthly',
                    customBillingFrequency: null,
                    estimateNotes: 'Canone indicativo per ciascun utente abilitato.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::Exact,
                        '29',
                        null,
                        'EUR',
                        BillingFrequency::Custom,
                        'al mese per utente',
                    ),
                    recommended: false,
                    implemented: false,
                    sortOrder: 2,
                ),
            ],
            evidences: [],
            resolutionNotes: null,
            resolvedAt: null,
            resolvedAtLabel: null,
            pagePlan: $settings['alternative_solutions'] === true
                ? new FindingPagePlanData([4, 5], [], false)
                : new FindingPagePlanData([4], [], false),
            riskMatrix: null,
        );
    }

    /** @param array<string, bool|int|string|null> $settings */
    private function nasNotificationFinding(array $settings): ReportFindingData
    {
        return new ReportFindingData(
            id: 3,
            number: 3,
            includeInReport: true,
            title: 'Notifiche del NAS non configurate',
            category: 'NAS e Storage',
            scopeType: 'selected_assets',
            scopeLabel: 'Asset selezionati',
            scopeDescription: null,
            sites: ['Sede principale'],
            assets: [
                new ReportAssetData(3, 'NAS ufficio', 'NAS', 'Sede principale', 'Vendor Demo', 'NAS-8B', 'nas-demo', '192.0.2.30', 'NAS ufficio'),
            ],
            consequenceLabel: 'Seria',
            consequenceColor: '#B42318',
            likelihoodLabel: 'Possibile',
            likelihoodColor: '#D97706',
            priorityLabel: 'Moderata',
            priorityColor: '#D97706',
            priorityOverridden: true,
            priorityRationale: 'Il controllo manuale giornaliero riduce temporaneamente l’urgenza, senza eliminare il problema.',
            problem: 'Il NAS non invia notifiche relative a guasti, volumi degradati, errori dei dischi o attività di backup non riuscite.',
            entrepreneurNotes: 'Senza notifiche, un problema può rimanere inosservato fino a quando causa un’interruzione o rende indisponibili i dati.',
            technicalNotes: 'Verificare eventi supportati, canale SMTP o webhook e consegna effettiva degli avvisi.',
            status: 'resolved',
            statusLabel: 'Risolto',
            solutions: [
                new ReportSolutionData(
                    id: 6,
                    title: 'Configurare e verificare le notifiche',
                    description: 'Configurare un canale affidabile per gli eventi critici e verificare con un test che gli avvisi raggiungano i destinatari.',
                    comparisonNotes: null,
                    effortLabel: 'Basso',
                    effortNotes: 'Attività breve comprensiva di una prova reale di consegna.',
                    estimateType: 'bundled',
                    amountMin: null,
                    amountMax: null,
                    currencyCode: null,
                    billingFrequency: 'one_off',
                    customBillingFrequency: null,
                    estimateNotes: 'Compresa nelle attività periodiche di configurazione.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::Bundled,
                        null,
                        null,
                        null,
                        BillingFrequency::OneOff,
                    ),
                    recommended: true,
                    implemented: true,
                    sortOrder: 1,
                ),
                new ReportSolutionData(
                    id: 7,
                    title: 'Integrare il NAS nel monitoraggio centralizzato',
                    description: 'Raccogliere stato, capacità ed eventi del NAS nella piattaforma di monitoraggio con escalation degli allarmi.',
                    comparisonNotes: 'Aggiunge supervisione continua e storico, con un piccolo costo ricorrente.',
                    effortLabel: 'Medio',
                    effortNotes: 'Richiede credenziali dedicate e configurazione delle soglie.',
                    estimateType: 'exact',
                    amountMin: '18',
                    amountMax: null,
                    currencyCode: 'EUR',
                    billingFrequency: 'monthly',
                    customBillingFrequency: null,
                    estimateNotes: 'Canone mensile indicativo per dispositivo.',
                    estimateLabel: $this->estimateLabel(
                        $settings,
                        EstimateType::Exact,
                        '18',
                        null,
                        'EUR',
                        BillingFrequency::Monthly,
                    ),
                    recommended: false,
                    implemented: false,
                    sortOrder: 2,
                ),
            ],
            evidences: [
                new ReportEvidenceData(
                    id: 1,
                    type: 'file',
                    title: 'Schermata di verifica notifiche',
                    filePath: null,
                    url: null,
                    originalFilename: 'valid-small.png',
                    caption: 'Esempio di didascalia configurabile per una evidenza inclusa.',
                    mimeType: 'image/png',
                    sizeBytes: 68,
                    sha256: hash_file('sha256', base_path('fixtures/evidence/valid-small.png')) ?: null,
                    included: true,
                    sortOrder: 1,
                    imageDataUri: 'data:image/png;base64,'.base64_encode(
                        (string) file_get_contents(base_path('fixtures/evidence/valid-small.png')),
                    ),
                ),
                new ReportEvidenceData(
                    id: 2,
                    type: 'url',
                    title: 'Verbale della prova notifiche',
                    filePath: null,
                    url: 'https://example.invalid/verifica-notifiche-nas',
                    originalFilename: null,
                    caption: null,
                    mimeType: null,
                    sizeBytes: null,
                    sha256: null,
                    included: true,
                    sortOrder: 2,
                    imageDataUri: null,
                ),
            ],
            resolutionNotes: 'Canale SMTP configurato e prova di consegna completata con esito positivo.',
            resolvedAt: '2026-07-23T14:30:00+00:00',
            resolvedAtLabel: '23/07/2026 16:30',
            pagePlan: $settings['alternative_solutions'] === true
                ? new FindingPagePlanData([6, 7], [], false)
                : new FindingPagePlanData([6], [], false),
            riskMatrix: null,
        );
    }

    /**
     * @param  array<string, bool|int|string|null>  $settings
     */
    private function estimateLabel(
        array $settings,
        EstimateType $estimateType,
        ?string $amountMin,
        ?string $amountMax,
        ?string $currencyCode,
        BillingFrequency $billingFrequency,
        ?string $customBillingFrequency = null,
    ): string {
        return $this->formatEstimate->format(
            estimateType: $estimateType,
            amountMin: $amountMin,
            amountMax: $amountMax,
            currencyCode: $currencyCode,
            billingFrequency: $billingFrequency,
            customBillingFrequency: $customBillingFrequency,
            displayCurrency: (string) $settings['currency'],
            currencySymbol: (string) $settings['currency_symbol'],
            currencySymbolPosition: (string) $settings['currency_symbol_position'],
            currencyDecimals: (int) $settings['currency_decimals'],
        );
    }
}
