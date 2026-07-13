<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>{{ $assessment->title }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        body { color: #1f2937; font-family: "DejaVu Sans", sans-serif; font-size: 10pt; line-height: 1.4; }
        h1 { color: #1e3a5f; font-size: 22pt; margin: 12mm 0 4mm; }
        h2 { color: #1e3a5f; font-size: 15pt; margin: 7mm 0 3mm; }
        .meta { color: #4b5563; margin-bottom: 8mm; }
        .summary { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .summary th, .summary td { border: 1px solid #cbd5e1; padding: 5px; vertical-align: top; }
        .summary th { background: #e8eef6; color: #1e3a5f; text-align: left; }
        .summary .number { width: 8%; }
        .summary .priority { width: 16%; }
        .multiline { white-space: pre-line; }
        .page-break { page-break-before: always; }
        .finding { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>AssestMe — Proof Milestone 0</h1>
    <p class="meta">
        <strong>{{ $assessment->title }}</strong><br>
        Data assessment: {{ $assessment->assessment_date->format('d/m/Y') }}<br>
        Verifica caratteri italiani: priorità, continuità, attività, qualità.
    </p>

    <h2>Riepilogo finding</h2>
    <table class="summary">
        <thead>
            <tr>
                <th class="number">N.</th>
                <th>Titolo</th>
                <th class="priority">Priorità</th>
                <th>Soluzione raccomandata</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assessment->findings->take(12) as $finding)
                <tr>
                    <td>{{ $finding->sort_order }}</td>
                    <td>{{ $finding->title }}</td>
                    <td>{{ $finding->priority?->value ?? 'Non definita' }}</td>
                    <td class="multiline">{{ $finding->recommended_solution_summary }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>Dettaglio finding</h2>
    @forelse ($assessment->findings->take(3) as $finding)
        <section class="finding">
            <h2>{{ $finding->sort_order }}. {{ $finding->title }}</h2>
            <p><strong>Problema</strong></p>
            <p class="multiline">{{ $finding->problem }}</p>
            <p><strong>Note per l’imprenditore</strong></p>
            <p class="multiline">{{ $finding->entrepreneur_notes }}</p>
            <p><strong>Soluzione raccomandata</strong></p>
            <p class="multiline">{{ $finding->recommended_solution_summary }}</p>
        </section>
    @empty
        <p>Nessun finding presente.</p>
    @endforelse
</body>
</html>
