<?php

namespace App\Filament\Shared\Pages;

use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Tickets\Enums\TicketPriority;
use App\Domain\Tickets\Enums\TicketStatus;
use App\Domain\Tickets\Services\AttachmentService;
use App\Domain\Tickets\Services\PriorityGuidelineService;
use App\Domain\Tickets\Services\TicketObserverService;
use App\Domain\Tickets\Services\TicketService;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\Presentation\TicketEventPresenter;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * @property Ticket $record
 * @property-read Schema $composerForm
 */
abstract class ViewTicket extends ViewRecord
{
    protected string $view = 'filament.tickets.view';

    public ?array $commentDraft = [];

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->composerForm->fill(['body' => null, 'is_internal' => false]);
    }

    public function composerForm(Schema $schema): Schema
    {
        return $schema->statePath('commentDraft')->components([
            RichEditor::make('body')
                ->label('Tu respuesta')
                ->placeholder('Escribe una respuesta o comparte un avance…')
                ->required()->maxLength(20000)
                ->fileAttachments(false)
                ->linkProtocols(['http', 'https', 'mailto'])
                ->toolbarButtons([
                    ['bold', 'italic', 'underline', 'strike', 'link'],
                    ['bulletList', 'orderedList', 'blockquote', 'codeBlock'],
                    ['undo', 'redo'],
                ])
                ->minHeight('150px')->maxHeight('280px'),
            Toggle::make('is_internal')
                ->label('Nota interna')
                ->helperText('Solo visible para el equipo de soporte.')
                ->visible(fn () => auth()->user()->isInternal())
                ->default(false),
        ]);
    }

    public function submitComment(): void
    {
        $this->refreshTicket();
        Gate::authorize('comment', $this->record);
        $data = $this->composerForm->getState();
        try {
            app(TicketService::class)->comment(
                auth()->user(), $this->record, $data['body'],
                (bool) ($data['is_internal'] ?? false), richText: true,
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['commentDraft.'.$field => $messages])->all()
            );
        }
        $this->composerForm->fill(['body' => null, 'is_internal' => false]);
        $this->refreshTicket();
        $this->dispatch('ticket-comment-posted');
        Notification::make()->title('Comentario enviado')->success()->send();
    }

    public function refreshTicket(): void
    {
        $fresh = app(TicketRepositoryInterface::class)
            ->findVisible(auth()->user(), $this->record->public_id);
        Gate::authorize('view', $fresh);
        // Keep the same model instance: cached infolists refer to it during this request.
        $this->record->setRawAttributes($fresh->getAttributes(), true);
        $this->record->setRelations($fresh->getRelations());
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('ticket_number')->label('Número')->copyable(), TextEntry::make('description')->label('Descripción')->columnSpanFull()->formatStateUsing(fn (string $state) => new HtmlString(nl2br(e($state))))->extraAttributes(['class' => 'desk-description']), TextEntry::make('contact_phone')->label('Número de contacto')->icon('heroicon-o-phone')->placeholder('Sin indicar')->copyable(), TextEntry::make('status')->label('Estado')->badge(), TextEntry::make('priority')->label('Prioridad')->badge(), TextEntry::make('category.name')->label('Categoría')->icon('heroicon-o-squares-plus'), TextEntry::make('assignee.name')->label('Agente')->icon('heroicon-o-user-circle')->placeholder('Sin asignar'), TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y H:i')->timezone('America/Santo_Domingo'), TextEntry::make('resolved_at')->label('Resuelto')->visible(fn (Ticket $record) => $record->resolved_at !== null)->dateTime('d/m/Y H:i')->timezone('America/Santo_Domingo'), TextEntry::make('closed_at')->label('Cerrado')->visible(fn (Ticket $record) => $record->closed_at !== null)->dateTime('d/m/Y H:i')->timezone('America/Santo_Domingo'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign')->color('gray')->icon('heroicon-o-user-plus')->label('Asignar agente')->visible(fn () => Gate::allows('update', $this->record) && $this->record->status->allowsAssignment())->schema([Select::make('assigned_to')->label('Agente')->options(fn () => User::query()->where('role', 'agent')->where('active', true)->pluck('name', 'id'))->required()])->action(function (array $data) {
                app(TicketService::class)->assign(auth()->user(), $this->record, (int) $data['assigned_to']);
                $this->refreshTicket();
            }),
            Action::make('priority')->color('gray')->icon('heroicon-o-flag')->label('Prioridad')->visible(fn () => Gate::allows('update', $this->record))->schema([Select::make('priority')->label('Prioridad')->options(TicketPriority::class)->required()])->action(function (array $data) {
                app(TicketService::class)->changePriority(auth()->user(), $this->record, $data['priority'] instanceof TicketPriority ? $data['priority'] : TicketPriority::from($data['priority']));
                $this->refreshTicket();
            }),
            Action::make('transition')->color('gray')->icon('heroicon-o-arrows-right-left')->label('Cambiar estado')->visible(fn () => Gate::allows('update', $this->record) && $this->record->status->allowedTransitions() !== [])->schema([Select::make('status')->label('Estado')->options(fn () => collect($this->record->status->allowedTransitions())->mapWithKeys(fn (TicketStatus $status) => [$status->value => $status->getLabel()])->all())->required(), Textarea::make('reason')->label('Justificación / solución')->helperText('Obligatoria para resolver, cancelar o reabrir.')->maxLength(10000)])->action(function (array $data) {
                app(TicketService::class)->transition(auth()->user(), $this->record, $data['status'] instanceof TicketStatus ? $data['status'] : TicketStatus::from($data['status']), $data['reason'] ?? null);
                $this->refreshTicket();
            }),
            Action::make('attach')->color('gray')->icon('heroicon-o-paper-clip')->label('Adjuntar archivo')->visible(fn () => Gate::allows('comment', $this->record))->schema([FileUpload::make('file')->label('Archivo')->storeFiles(false)->acceptedFileTypes(['application/pdf', 'image/png', 'image/jpeg', 'text/plain'])->maxSize(10240)->required()])->action(function (array $data) {
                app(AttachmentService::class)->upload(auth()->user(), $this->record, $data['file']);
                $this->refreshTicket();
            }),
        ];
    }

    public function observersAction(): Action
    {
        return Action::make('observers')
            ->label('Agregar observadores')->iconButton()->tooltip('Agregar observadores')->icon('heroicon-o-user-group')->color('gray')
            ->visible(fn () => Gate::allows('manageObservers', $this->record))
            ->modalHeading('Gestionar observadores')
            ->modalDescription('Participan en la conversación y reciben actualizaciones. Solo usuarios activos del equipo o de esta empresa.')
            ->fillForm(fn () => ['observer_ids' => app(TicketObserverService::class)->selectedIds(auth()->user(), $this->record)])
            ->schema([
                Select::make('observer_ids')->label('Agregar observadores')->multiple()->searchable()->preload()->maxItems(TicketObserverService::MAX_OBSERVERS)
                    ->options(fn () => app(TicketObserverService::class)->options(auth()->user(), $this->record)),
            ])
            ->action(function (array $data): void {
                app(TicketObserverService::class)->sync(auth()->user(), $this->record, $data['observer_ids'] ?? []);
                $this->refreshTicket();
                Notification::make()->title('Observadores actualizados')->success()->send();
            });
    }

    public function removeObserver(int $userId): void
    {
        app(TicketObserverService::class)->remove(auth()->user(), $this->record, $userId);
        $this->refreshTicket();
        Notification::make()->title('Observador retirado')->success()->send();
    }

    public function setObserverSubscription(bool $subscribed): void
    {
        app(TicketObserverService::class)->setSubscription(auth()->user(), $this->record, $subscribed);
        $this->refreshTicket();
    }

    public function isObserving(): bool
    {
        return $this->record->isObserver(auth()->user());
    }

    public function priorityGuideline(): array
    {
        return app(PriorityGuidelineService::class)->describe($this->record->priority);
    }

    public function participants(): array
    {
        return app(TicketObserverService::class)->participants(auth()->user(), $this->record);
    }

    public function visibleComments()
    {
        return TicketComment::query()->visibleTo(auth()->user())->where('ticket_id', $this->record->id)->with('user')->oldest()->get();
    }

    public function visibleEvents()
    {
        return $this->record->events()->with('actor')->when(! auth()->user()->isInternal(), fn ($q) => $q->whereRaw("COALESCE(metadata->>'internal', 'false') <> 'true'"))->latest('id')->get();
    }

    public function timeline(): array
    {
        return app(TicketEventPresenter::class)->present($this->visibleEvents(), auth()->user()->isInternal());
    }

    public function getHeading(): string
    {
        return $this->record->subject;
    }

    public function getSubheading(): ?string
    {
        return $this->record->ticket_number.' · '.$this->record->company->name;
    }
}
