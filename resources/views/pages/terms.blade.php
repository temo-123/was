@extends('layouts.app')

@section('title', __('Terms of use & gym rules'))

@section('content')
    <article class="legal bg-body p-4 rounded shadow-sm mx-auto" style="max-width: 860px">
        <h1 class="h3">{{ __('Terms of use & gym rules') }}</h1>
        <p class="text-body-secondary small">{{ __('Last updated') }}: {{ config('legal.updated_at') }}</p>

        <div class="alert alert-warning small">
            {{ __('Template text. Have it reviewed by a lawyer and adapt it to your gym before going live.') }}
        </div>

        <p>
            {{ __('These terms apply to everyone who visits :gym (":company", "we") and to everyone who uses this website or our app. By entering the gym or creating an account you accept them.', ['gym' => config('app.name'), 'company' => config('legal.company')]) }}
        </p>

        <h2>1. {{ __('Climbing is a risky activity') }}</h2>
        <p>{{ __('Climbing and bouldering involve a risk of falls and injuries, even when all rules are followed. You climb at your own risk and you are responsible for checking that your health allows you to climb. Tell the staff about any medical condition that may matter.') }}</p>

        <h2>2. {{ __('Registration') }}</h2>
        <ul>
            <li>{{ __('Before the first visit every visitor is registered with name, surname, ID number, date of birth and phone number, and confirms these rules.') }}</li>
            <li>{{ __('The information you give must be true. The price depends on your age, so we may ask for an ID document.') }}</li>
            <li>{{ __('Children under 16 may climb only with the written permission of a parent or legal guardian. Children under 10 must be supervised by an adult at all times.') }}</li>
        </ul>

        <h2>3. {{ __('Safety rules') }}</h2>
        <ul>
            <li>{{ __('Follow the instructions of the staff. First-time visitors must listen to the safety briefing.') }}</li>
            <li>{{ __('Never stand or walk under someone who is climbing. Keep the landing zone free of bags, bottles and people.') }}</li>
            <li>{{ __('Only one person per route / bouldering line at a time.') }}</li>
            <li>{{ __('Climb down when possible; jump only from a safe height and land on both feet.') }}</li>
            <li>{{ __('Rope climbing only for people who passed the gym\'s belay check. Use only certified equipment.') }}</li>
            <li>{{ __('Use climbing shoes or clean sports shoes. No climbing barefoot.') }}</li>
            <li>{{ __('Alcohol, drugs and climbing under their influence are forbidden.') }}</li>
            <li>{{ __('Report damaged or loose holds and any accident to the staff immediately.') }}</li>
        </ul>

        <h2>4. {{ __('Tickets and subscriptions') }}</h2>
        <ul>
            <li>{{ __('Prices are published on this website and at the reception. The price depends on age, time of entry and ticket type.') }}</li>
            <li>{{ __('A subscription is personal and cannot be transferred. It is valid for the period and hours it was bought for.') }}</li>
            <li>{{ __('Paid tickets and subscriptions are not refundable, except where required by law.') }}</li>
            <li>{{ __('We may change prices; already paid subscriptions keep their price.') }}</li>
        </ul>

        <h2>5. {{ __('Breaking the rules') }}</h2>
        <p>{{ __('Staff may stop anyone who endangers themselves or others and may refuse entry to people who repeatedly break the rules, without a refund.') }}</p>

        <h2>6. {{ __('Liability') }}</h2>
        <p>{{ __('We are responsible for keeping the walls, mats and equipment in a safe condition. To the extent allowed by Georgian law, we are not liable for injuries caused by ignoring these rules or staff instructions, or for lost or stolen personal belongings.') }}</p>

        <h2>7. {{ __('Website and account') }}</h2>
        <ul>
            <li>{{ __('Keep your password secret. You are responsible for what is done with your account.') }}</li>
            <li>{{ __('You may sign in with Google; then Google\'s terms also apply to the sign-in.') }}</li>
            <li>{{ __('You can delete your account at any time from your profile page.') }}</li>
            <li>{{ __('Personal data is handled as described in the') }} <a href="{{ route('privacy') }}">{{ __('Privacy policy') }}</a>.</li>
        </ul>

        <h2>8. {{ __('Changes and law') }}</h2>
        <p>{{ __('We may update these terms; the current version is always on this page. These terms are governed by the laws of Georgia.') }}</p>

        <h2>9. {{ __('Contact') }}</h2>
        <p>{{ config('legal.company') }} · {{ config('legal.address') }} · <a href="mailto:{{ config('legal.email') }}">{{ config('legal.email') }}</a> · {{ config('legal.phone') }}</p>
    </article>
@endsection
