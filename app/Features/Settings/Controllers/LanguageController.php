<?php

namespace App\Features\Settings\Controllers;

use App\DataTransferObjects\UserSettings;
use App\Features\Settings\Requests\SaveLanguageRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class LanguageController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings::settings.language.edit', [
            'currentLang' => $request->user()->settings->lang,
        ]);
    }

    public function update(SaveLanguageRequest $request)
    {
        $request->user()->update([
            'settings' => new UserSettings(lang: $request->input('lang')),
        ]);

        InAppNotification::success(__('Language updated.'));

        return back();
    }
}
