<?php

namespace Juzaweb\Frontend\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Juzaweb\CMS\Facades\HookAction;
use Juzaweb\CMS\Http\Controllers\FrontendController;
use Juzaweb\Frontend\Http\Requests\ChangePasswordRequest;
use Juzaweb\Frontend\Http\Requests\UpdateProfileRequest;

class ProfileController extends FrontendController
{
    public function index($slug = null)
    {
        $title = trans('cms::app.profile');

        $pages = apply_filters(
            'theme.profile.pages',
            HookAction::getProfilePages()->toArray()
        );

        return $this->view(
            'theme::profile.index',
            compact(
                'title',
                'slug',
                'pages'
            )
        );
    }

    public function update(UpdateProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = Auth::user();

        $update = $request->only(['name']);

        if ($password = $request->input('password')) {
            $update['password'] = Hash::make($password);
        }

        $user->update($update);

        return $this->success(
            [
                'message' => trans('cms::app.update_successfully'),
            ]
        );
    }

    public function changePassword()
    {
        $title = trans('cms::app.change_password');
        $user = Auth::user();

        return $this->view(
            'theme::profile.change_password',
            compact(
                'title',
                'user'
            )
        );
    }

    public function notification()
    {
        $title = trans('cms::app.profile');
        $user = Auth::user();
        $notifications = $user->notifications->toArray();

        return $this->view(
            'theme::profile.notification.index',
            compact(
                'title',
                'notifications',
                'user'
            )
        );
    }

    public function doChangePassword(ChangePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $currentPassword = $request->post('current_password');
        $password = $request->post('password');
        $user = Auth::user();

        if (!Hash::check($currentPassword, $user->password)) {
            return response()->json(
                [
                    'status' => 'error',
                    'message' => trans('cms::app.current_password_incorrect'),
                ]
            );
        }

        $user->update(
            [
                'password' => Hash::make($password),
            ]
        );

        return $this->success(
            [
                'message' => trans('cms::app.change_password_successfully'),
            ]
        );
    }
}