<?php

use App\Models\CreditOrder;
use App\Models\Project;
use App\Models\User;
use App\Models\VideoRender;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.admin'), Title('Admin overview')] class extends Component
{
    public function with(): array
    {
        return [
            'tiles' => [
                ['label' => 'Users', 'value' => User::count(), 'icon' => 'fa-users', 'bg' => 'bg-info'],
                ['label' => 'Projects', 'value' => Project::count(), 'icon' => 'fa-folder', 'bg' => 'bg-primary'],
                ['label' => 'Total renders', 'value' => VideoRender::count(), 'icon' => 'fa-film', 'bg' => 'bg-secondary'],
                ['label' => 'Renders today', 'value' => VideoRender::whereDate('created_at', today())->count(), 'icon' => 'fa-calendar-day', 'bg' => 'bg-warning'],
                ['label' => 'Completed', 'value' => VideoRender::where('status', VideoRender::STATUS_DONE)->count(), 'icon' => 'fa-check-circle', 'bg' => 'bg-success'],
                ['label' => 'Failed', 'value' => VideoRender::where('status', VideoRender::STATUS_FAILED)->count(), 'icon' => 'fa-times-circle', 'bg' => 'bg-danger'],
                ['label' => 'Paid orders', 'value' => CreditOrder::where('status', CreditOrder::STATUS_PAID)->count(), 'icon' => 'fa-receipt', 'bg' => 'bg-primary'],
                ['label' => 'Revenue (BDT)', 'value' => number_format(CreditOrder::where('status', CreditOrder::STATUS_PAID)->sum('amount')), 'icon' => 'fa-money-bill', 'bg' => 'bg-success'],
            ],
            'recentRenders' => VideoRender::with('project.user')->latest()->take(10)->get(),
        ];
    }
}; ?>

<div>
    <h1 class="mb-4">Overview</h1>

    <div class="row">
        @foreach ($tiles as $tile)
            <div class="col-6 col-lg-3">
                <div class="small-box {{ $tile['bg'] }}">
                    <div class="inner">
                        <h3>{{ $tile['value'] }}</h3>
                        <p>{{ $tile['label'] }}</p>
                    </div>
                    <div class="icon"><i class="fas {{ $tile['icon'] }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Latest renders</h3></div>
        <div class="card-body table-responsive p-0">
            <table class="table table-striped">
                <thead>
                    <tr><th>Project</th><th>User</th><th>Status</th><th>Created</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentRenders as $render)
                        <tr wire:key="ar-{{ $render->id }}">
                            <td>{{ $render->project?->title ?? '—' }}</td>
                            <td class="text-muted">{{ $render->project?->user?->email }}</td>
                            <td><span class="text-capitalize">{{ $render->status }}</span></td>
                            <td class="text-muted">{{ $render->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No renders yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
