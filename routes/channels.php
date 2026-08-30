<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('projects.{project}', function (User $user, int $project) {
    return Project::whereKey($project)->where('user_id', $user->id)->exists();
});
