<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Publicly reachable informational pages.
 *
 * Google's OAuth brand verification requires the app to expose a public home
 * page, privacy policy and terms of service on the same domain that owns the
 * OAuth client. These pages are intentionally reachable without a session.
 */
class PublicPageController extends Controller
{
    /**
     * Marketing page describing what GaweTracker does.
     */
    public function about(): View
    {
        return view('public.about');
    }

    /**
     * Privacy policy, including how connected Gmail data is handled.
     */
    public function privacy(): View
    {
        return view('public.privacy');
    }

    /**
     * Terms of service.
     */
    public function terms(): View
    {
        return view('public.terms');
    }
}
