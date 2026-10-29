<?php

namespace App\Providers;

use App\Domain\Companies\Contracts\CompanyRepositoryInterface;
use App\Domain\Companies\Repositories\EloquentCompanyRepository;
use App\Domain\Tickets\Contracts\TicketRepositoryInterface;
use App\Domain\Tickets\Repositories\EloquentTicketRepository;
use App\Domain\Tickets\Support\CommentContent;
use App\Domain\Users\Contracts\UserRepositoryInterface;
use App\Domain\Users\Repositories\EloquentUserRepository;
use App\Models\Company;
use App\Models\PriorityGuideline;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketCategory;
use App\Models\TicketComment;
use App\Models\User;
use App\Policies\CategoryPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\PriorityGuidelinePolicy;
use App\Policies\TicketAttachmentPolicy;
use App\Policies\TicketCommentPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CommentContent::class);
        $this->app->bind(CompanyRepositoryInterface::class, EloquentCompanyRepository::class);
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(TicketRepositoryInterface::class, EloquentTicketRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(PriorityGuideline::class, PriorityGuidelinePolicy::class);
        Gate::define('viewReports', fn (User $user): bool => $user->isEnabled() && $user->isInternal());
        foreach ([Company::class => CompanyPolicy::class, User::class => UserPolicy::class, Ticket::class => TicketPolicy::class, TicketCategory::class => CategoryPolicy::class, TicketComment::class => TicketCommentPolicy::class, TicketAttachment::class => TicketAttachmentPolicy::class] as $model => $policy) {
            Gate::policy($model, $policy);
        }
        DateTimePicker::configureUsing(fn ($component) => $component->timezone('America/Santo_Domingo'));
        TextColumn::configureUsing(fn ($column) => $column->timezone('America/Santo_Domingo'));
    }
}
