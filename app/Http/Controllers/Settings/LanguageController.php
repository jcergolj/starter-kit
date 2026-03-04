<?php

namespace App\Http\Controllers\Settings;

use App\DataTransferObjects\UserSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveLanguageRequest;
use Illuminate\Http\Request;
use Jcergolj\InAppNotifications\Facades\InAppNotification;

class LanguageController extends Controller
{
    public function edit(Request $request)
    {
        return view('settings.language.edit', [
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
