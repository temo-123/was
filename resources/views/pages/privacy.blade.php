@extends('layouts.app')

@section('title', __('Privacy policy'))

@section('content')
    <article class="legal bg-body p-4 rounded shadow-sm mx-auto" style="max-width: 860px">
        <h1 class="h3">{{ __('Privacy policy') }}</h1>
        <p class="text-body-secondary small">{{ __('Last updated') }}: {{ config('legal.updated_at') }}</p>

        <div class="alert alert-warning small">
            {{ __('Template text. Have it reviewed by a lawyer and adapt it to your gym before going live.') }}
        </div>

        <p>{{ __(':company (":gym", "we") is the controller of the personal data described below. We process it according to the Law of Georgia on Personal Data Protection.', ['company' => config('legal.company'), 'gym' => config('app.name')]) }}</p>

        <h2>1. {{ __('What data we collect') }}</h2>
        <ul>
            <li><strong>{{ __('Visitor card') }}:</strong> {{ __('first name, last name, ID / passport number, date of birth, phone number, optional email, staff notes (e.g. health remarks you tell us), category (e.g. member), the date you accepted the gym rules.') }}</li>
            <li><strong>{{ __('Visits and payments') }}:</strong> {{ __('date and time of each entry, ticket or subscription, price and payment method, staff member who registered it.') }}</li>
            <li><strong>{{ __('Online account') }}:</strong> {{ __('name, email, password (stored only as a secure hash), optional phone, ID number and date of birth. If you sign in with Google: your Google account ID, name, email and profile picture.') }}</li>
            <li><strong>{{ __('Technical data') }}:</strong> {{ __('IP address, browser information and cookies needed to keep you signed in and to protect the site (session and security cookies only; no advertising cookies).') }}</li>
        </ul>

        <h2>2. {{ __('Why we use it') }}</h2>
        <ul>
            <li>{{ __('To register your entries and sell tickets and subscriptions (contract).') }}</li>
            <li>{{ __('To calculate the correct price — it depends on your age (contract).') }}</li>
            <li>{{ __('For your safety and to contact you or your guardian in an emergency (legitimate interest / vital interest).') }}</li>
            <li>{{ __('To keep accounting records required by law (legal obligation).') }}</li>
            <li>{{ __('To give you online access to your visits and subscriptions (contract / your consent).') }}</li>
        </ul>

        <h2>3. {{ __('Connecting your account with your visitor card') }}</h2>
        <p>{{ __('If you register online with the same ID number, or with a verified email that matches your visitor card, we connect them automatically so you can see your history. The reception can also connect or disconnect them at your request.') }}</p>

        <h2>4. {{ __('Who can see it') }}</h2>
        <p>{{ __('Only authorised gym staff, each with the minimum access their job needs. We use service providers for hosting, email delivery and Google sign-in; they process data only on our instructions. We do not sell your data. We share it with authorities only when the law requires it.') }}</p>

        <h2>5. {{ __('How long we keep it') }}</h2>
        <ul>
            <li>{{ __('Visitor card and visit history: while you visit the gym and up to :years years after your last visit.', ['years' => 3]) }}</li>
            <li>{{ __('Payment records: as long as Georgian tax and accounting law requires.') }}</li>
            <li>{{ __('Online account: until you delete it. Deleting the account does not delete the visitor card automatically — ask us if you want that too.') }}</li>
        </ul>

        <h2>6. {{ __('Children') }}</h2>
        <p>{{ __('Data of visitors under 16 is provided and managed by their parent or guardian, who can exercise the rights below on the child\'s behalf.') }}</p>

        <h2>7. {{ __('Your rights') }}</h2>
        <p>{{ __('You can ask for access to your data, a copy of it, correction, deletion, restriction of processing, and you can object to processing or withdraw consent. Write to us at the address below. You can also complain to the Personal Data Protection Service of Georgia.') }}</p>

        <h2>8. {{ __('Security') }}</h2>
        <p>{{ __('Passwords are hashed, the site uses encrypted connections (HTTPS), staff access is controlled with roles and permissions, and important actions are logged.') }}</p>

        <h2>9. {{ __('Contact') }}</h2>
        <p>{{ config('legal.company') }} · {{ config('legal.address') }} · <a href="mailto:{{ config('legal.email') }}">{{ config('legal.email') }}</a> · {{ config('legal.phone') }}</p>
    </article>
@endsection
