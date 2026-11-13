<?php

namespace Lunar\Admin\Support\Actions;

use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Lunar\Admin\Support\Livewire\FileDownload;

class PdfDownload extends Action
{
    protected string $pdfView = '';

    protected Closure|string|null $filename = null;

    public function pdfView(string $pdfView): self
    {
        $this->pdfView = $pdfView;

        return $this;
    }

    public function filename(Closure|string|null $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (config('lunar.panel.pdf_rendering', 'download') == 'stream') {
            $this->url(function ($record) {
                return URL::temporarySignedRoute(
                    'lunar.pdf.download',
                    now()->addMinutes(2),
                    [
                        'record' => $record->id,
                        'record_type' => $record->getMorphClass(),
                        'view' => $this->evaluate($this->pdfView),
                    ]
                );
            }, shouldOpenInNewTab: true);
        } else {
            $this->action(function ($record, $livewire) {
                Notification::make()->title(
                    __('lunarpanel::order.action.download_order_pdf.notification')
                )->success()->send();

                $name = $this->evaluate($this->filename);

                FileDownload::queue(
                    $livewire,
                    Pdf::loadView($this->evaluate($this->pdfView), [
                        'record' => $record,
                    ])->output(),
                    is_string($name) && $name !== '' ? $name : 'download.pdf',
                );
            });
        }
    }
}
