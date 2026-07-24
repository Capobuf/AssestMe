<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Data\Reports\AssessmentReportData;
use App\Data\Reports\ReportAssetData;
use App\Data\Reports\ReportEvidenceData;
use App\Data\Reports\ReportFindingData;
use App\Data\Reports\ReportPriorityData;
use App\Data\Reports\ReportSolutionData;
use RuntimeException;

final class PdfRendererSpikeReportFactory
{
    public function make(): AssessmentReportData
    {
        return new AssessmentReportData(
            generatedAt: '2026-07-24T08:30:00+00:00',
            applicationVersion: 'D-059-spike',
            locale: 'it',
            title: 'Assessment sicurezza — VIP Estintori',
            assessmentId: 59009,
            assessmentTitle: 'Assessment infrastruttura e continuità operativa',
            assessmentDate: '2026-07-24',
            assessmentDateLabel: '24/07/2026',
            assessmentStatus: 'draft',
            assessmentStatusLabel: 'Bozza tecnica',
            assessmentScope: 'Intera organizzazione',
            scopeDescription: 'Sede operativa e infrastruttura informatica connessa.',
            assessmentSites: ['Sede operativa di Montebelluna'],
            introduction: 'Proof deterministico Chrome-first per verificare impaginazione, orientamenti, contatori e blocchi semantici.',
            executiveSummary: 'La configurazione osservata richiede interventi sulla segmentazione, sulle copie di sicurezza e sul presidio degli accessi.',
            methodologyNotes: 'La fixture riproduce un caso realistico senza dipendere dal database di sviluppo.',
            clientId: 59009,
            clientName: 'VIP Estintori',
            clientLegalName: 'VIP Estintori S.r.l.',
            clientVatNumber: 'IT01234567890',
            clientTaxCode: null,
            clientEmail: 'sicurezza@vip-estintori.example',
            clientPhone: '+39 0423 000000',
            clientWebsite: 'https://vip-estintori.example',
            clientAddress: 'Via dell’Industria 18, 31044 Montebelluna (TV), Italia',
            logos: [],
            priorityLegend: $this->priorities(),
            findings: $this->findings(),
            settingsSnapshot: [
                'renderer_spike' => true,
                'finding_page_budget' => 2,
                'solution_budget' => 3,
            ],
        );
    }

    /** @return list<ReportPriorityData> */
    private function priorities(): array
    {
        return [
            new ReportPriorityData('critical', 'Critica', 'Intervento immediato', '#B42318', 1),
            new ReportPriorityData('high', 'Alta', 'Pianificare a breve termine', '#D97706', 2),
            new ReportPriorityData('medium', 'Media', 'Ridurre il rischio nel ciclo ordinario', '#2563EB', 3),
            new ReportPriorityData('low', 'Bassa', 'Monitorare e migliorare', '#15803D', 4),
        ];
    }

    /** @return list<ReportFindingData> */
    private function findings(): array
    {
        return [
            $this->finding(
                number: 1,
                title: 'Rete piatta fra uffici e dispositivi di sicurezza',
                problem: 'La centrale antincendio con interfaccia di rete condivide il segmento degli endpoint d’ufficio. Un dispositivo compromesso potrebbe raggiungere sistemi operativi e dispositivi tecnici senza un controllo intermedio.',
                priority: 'Critica',
                priorityColor: '#B42318',
                consequence: 'Molto alta',
                likelihood: 'Probabile',
                solutions: [
                    $this->solution(101, 'Segmentazione VLAN dei dispositivi tecnici', 'Separare gli apparati di sicurezza, limitare i flussi necessari e registrare ogni eccezione sul firewall.', true, false, 1),
                    $this->solution(102, 'Isolamento fisico della centrale', 'Predisporre una rete tecnica dedicata con accesso amministrativo controllato.', false, true, 2),
                ],
                assets: [
                    new ReportAssetData(
                        id: 101,
                        name: 'Centrale antincendio reparto tecnico',
                        type: 'Dispositivo di sicurezza',
                        site: 'Sede operativa di Montebelluna',
                        manufacturer: 'Notifier',
                        model: 'AM-8200N',
                        hostname: 'fire-panel-01',
                        ipAddress: '192.168.10.47',
                        displayLabel: 'Centrale antincendio reparto tecnico — Notifier AM-8200N — 192.168.10.47',
                    ),
                ],
                evidences: [$this->evidence()],
            ),
            $this->finding(
                number: 2,
                title: 'Copie di sicurezza non isolate e ripristino non provato',
                problem: 'Le copie dei documenti amministrativi e tecnici restano raggiungibili con le stesse credenziali usate nelle attività quotidiane. Non è disponibile un verbale recente di ripristino e non sono formalizzati obiettivi di recupero. Il finding viene diviso in due contenitori semantici deterministici per misurare lo spazio: la futura distribuzione sarà governata da budget testuali approvati, non da coordinate del browser.',
                priority: 'Critica',
                priorityColor: '#B42318',
                consequence: 'Molto alta',
                likelihood: 'Probabile',
                solutions: [
                    $this->solution(201, 'Backup immutabile fuori dominio', 'Aggiungere una copia cifrata e immutabile, separata dalle credenziali ordinarie, con retention documentata e controllo giornaliero.', true, false, 1),
                    $this->solution(202, 'Repository offline a rotazione', 'Introdurre supporti offline a rotazione custoditi in sede separata e sottoposti a verifica mensile.', false, true, 2),
                    $this->solution(203, 'Servizio gestito di backup', 'Affidare copia, monitoraggio e test di recupero a un fornitore qualificato con responsabilità e tempi contrattuali.', false, false, 3),
                ],
            ),
            $this->finding(
                number: 3,
                title: 'Account amministrativi condivisi',
                problem: 'Gli interventi sui sistemi principali utilizzano un account amministrativo condiviso. Le attività non sono attribuibili al singolo operatore e la revoca degli accessi non è tempestiva.',
                priority: 'Alta',
                priorityColor: '#D97706',
                consequence: 'Alta',
                likelihood: 'Possibile',
                solutions: [
                    $this->solution(301, 'Identità amministrative nominative', 'Creare account nominativi separati, proteggere l’elevazione con MFA e conservare i log amministrativi.', true, true, 1),
                ],
            ),
            $this->finding(
                number: 4,
                title: 'Inventario delle configurazioni incompleto',
                problem: 'Le configurazioni essenziali non hanno un inventario aggiornato con proprietario, ultima revisione e dipendenze. Il recupero durante un fermo dipende dalla memoria dei tecnici.',
                priority: 'Media',
                priorityColor: '#2563EB',
                consequence: 'Media',
                likelihood: 'Possibile',
                solutions: [
                    $this->solution(401, 'Registro controllato delle configurazioni', 'Raccogliere configurazioni, dipendenze e responsabilità in un registro versionato sottoposto a revisione trimestrale.', true, true, 1),
                ],
            ),
        ];
    }

    /**
     * @param  list<ReportSolutionData>  $solutions
     * @param  list<ReportAssetData>  $assets
     * @param  list<ReportEvidenceData>  $evidences
     */
    private function finding(
        int $number,
        string $title,
        string $problem,
        string $priority,
        string $priorityColor,
        string $consequence,
        string $likelihood,
        array $solutions,
        array $assets = [],
        array $evidences = [],
    ): ReportFindingData {
        return new ReportFindingData(
            id: 59000 + $number,
            number: $number,
            includeInReport: true,
            title: $title,
            category: 'Sicurezza e continuità',
            scopeType: 'organization',
            scopeLabel: 'Intera organizzazione',
            scopeDescription: null,
            sites: ['Sede operativa di Montebelluna'],
            assets: $assets,
            consequenceLabel: $consequence,
            consequenceColor: $priorityColor,
            likelihoodLabel: $likelihood,
            likelihoodColor: $priorityColor,
            priorityLabel: $priority,
            priorityColor: $priorityColor,
            priorityOverridden: false,
            priorityRationale: null,
            problem: $problem,
            entrepreneurNotes: null,
            technicalNotes: null,
            status: 'open',
            statusLabel: 'Aperto',
            solutions: $solutions,
            evidences: $evidences,
            resolutionNotes: null,
            resolvedAt: null,
            resolvedAtLabel: null,
        );
    }

    private function solution(
        int $id,
        string $title,
        string $description,
        bool $recommended,
        bool $implemented,
        int $sortOrder,
    ): ReportSolutionData {
        return new ReportSolutionData(
            id: $id,
            title: $title,
            description: $description,
            comparisonNotes: 'Confronto deterministico per il proof D-059.',
            effortLabel: $sortOrder === 1 ? 'Medio' : 'Alto',
            effortNotes: null,
            estimateType: 'requires_quote',
            amountMin: null,
            amountMax: null,
            currencyCode: null,
            billingFrequency: 'one_off',
            customBillingFrequency: null,
            estimateNotes: null,
            estimateLabel: 'Richiede preventivo',
            recommended: $recommended,
            implemented: $implemented,
            sortOrder: $sortOrder,
        );
    }

    private function evidence(): ReportEvidenceData
    {
        $path = base_path('fixtures/report-spike/networked-fire-panel-evidence.jpg');
        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            throw new RuntimeException("The renderer spike evidence is unreadable: {$path}");
        }

        return new ReportEvidenceData(
            id: 59001,
            type: 'file',
            title: 'Interfaccia di rete della centrale antincendio',
            filePath: 'renderer-spike/networked-fire-panel-evidence.jpg',
            url: null,
            originalFilename: 'networked-fire-panel-evidence.jpg',
            caption: 'Porta di rete attiva sul dispositivo tecnico verificato durante il sopralluogo.',
            mimeType: 'image/jpeg',
            sizeBytes: strlen($contents),
            sha256: hash('sha256', $contents),
            included: true,
            sortOrder: 1,
            imageDataUri: 'data:image/jpeg;base64,'.base64_encode($contents),
        );
    }
}
