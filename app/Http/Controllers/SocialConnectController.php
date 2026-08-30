<?php

namespace App\Http\Controllers;

use App\Services\Social\SocialPublisher;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SocialConnectController extends Controller
{
    public function connect(SocialPublisher $publisher)
    {
        abort_unless($publisher->isConfigured(), Response::HTTP_NOT_FOUND);

        return redirect()->away($publisher->connectUrl($this->userOrFail()));
    }

    public function callback(Request $request, SocialPublisher $publisher)
    {
        abort_unless($publisher->isConfigured(), Response::HTTP_NOT_FOUND);

        if ($request->filled('error')) {
            return redirect()->route('social.index')->with('error', 'Facebook connection was cancelled.');
        }

        $user = $publisher->userFromState((string) $request->query('state'));

        abort_unless($user && $user->is($request->user()), Response::HTTP_FORBIDDEN);

        $count = $publisher->linkAccounts($user, (string) $request->query('code'));

        return redirect()->route('social.index')
            ->with('status', "Linked {$count} account(s).");
    }

    private function userOrFail()
    {
        return request()->user() ?? abort(Response::HTTP_UNAUTHORIZED);
    }
}
