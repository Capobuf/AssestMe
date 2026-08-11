<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput\Actions\CopyAction;
use Illuminate\Support\Js;

final class ClipboardCopyAction extends CopyAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->alpineClickHandler(function (mixed $state): string {
            $copyableState = Js::from($state);
            $copyMessage = Js::from($this->getCopyMessage($state));
            $failureMessage = Js::from(__('assestme.fatture_in_cloud.notifications.callback_copy_failed'));
            $duration = Js::from($this->getCopyMessageDuration($state));

            return <<<JS
                (() => {
                    const showResult = (message) => \$tooltip(message, {
                        theme: \$store.theme,
                        timeout: {$duration},
                    })
                    const copied = () => showResult({$copyMessage})
                    const failed = () => showResult({$failureMessage})
                    const fallback = () => {
                        const textarea = document.createElement('textarea')
                        textarea.value = String({$copyableState} ?? '')
                        textarea.setAttribute('readonly', '')
                        textarea.style.position = 'fixed'
                        textarea.style.opacity = '0'
                        document.body.appendChild(textarea)
                        textarea.select()
                        textarea.setSelectionRange(0, textarea.value.length)

                        let successful = false
                        try {
                            successful = document.execCommand('copy')
                        } finally {
                            textarea.remove()
                        }

                        if (! successful) {
                            throw new Error('Clipboard copy failed')
                        }
                    }
                    const fallbackOrFail = () => {
                        try {
                            fallback()
                            copied()
                        } catch {
                            failed()
                        }
                    }

                    if (window.isSecureContext && window.navigator.clipboard?.writeText) {
                        window.navigator.clipboard.writeText(String({$copyableState} ?? ''))
                            .then(copied)
                            .catch(fallbackOrFail)

                        return
                    }

                    fallbackOrFail()
                })()
                JS;
        });
    }
}
