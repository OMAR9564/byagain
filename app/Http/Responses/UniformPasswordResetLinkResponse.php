<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes "reset my password" answer the same way whether or not the address has
 * an account here.
 *
 * Fortify's default failure response says "we can't find a user with that
 * email address", which turns the form into an account-existence oracle:
 * anyone can check whether a given person reads on byagain. The whole point of
 * FR-004 is that they cannot.
 *
 * Both outcomes now produce the same status and the same neutral message. The
 * failure path still sends no mail — it just stops announcing that.
 */
final class UniformPasswordResetLinkResponse implements FailedPasswordResetLinkRequestResponse
{
    /*
     * Fortify resolves this with app(..., ['status' => $status]). There is no
     * constructor to receive it, so the container simply drops the argument —
     * which is the point: reporting the broker's status is exactly the leak
     * this class exists to close.
     */

    public function toResponse($request): Response
    {
        // Deliberately identical to the success response, down to the key.
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => $this->message()], 200);
        }

        return $this->redirect($request);
    }

    private function redirect(Request $request): RedirectResponse
    {
        return redirect()->back()->with('status', $this->message());
    }

    private function message(): string
    {
        // `passwords.sent` rather than the broker's actual status, so a failed
        // lookup cannot be told apart from a successful one.
        return trans('passwords.sent');
    }
}
