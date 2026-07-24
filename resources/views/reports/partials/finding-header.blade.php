<?php declare(strict_types=1); ?>

<header class="finding-heading">
    <table class="finding-heading__table" role="presentation">
        <tr>
            <td class="finding-heading__number">
                {{ str_pad((string) $finding->number, 2, '0', STR_PAD_LEFT) }}
            </td>

            <td class="finding-heading__content">
                <h1 class="finding-title {{ $titleClass }}">
                    {{ $finding->title }}

                    @if ($continuation)
                        <span class="continuation-label">
                            — {{ __('assestme.reports.document.continues') }}
                        </span>
                    @endif
                </h1>

                <div class="finding-meta">
                    <span>
                        <i
                            class="priority-marker"
                            style="border-color: {{ $finding->priorityColor }}"
                        ></i>
                        {{ $finding->priorityLabel }}
                    </span>

                    <span class="status-{{ $finding->status }}">
                        {{ $statusGlyph($finding->status) }}
                        {{ $finding->statusLabel }}
                    </span>

                    @if (filled($finding->category))
                        <span>{{ $finding->category }}</span>
                    @endif
                </div>
            </td>
        </tr>
    </table>
</header>
