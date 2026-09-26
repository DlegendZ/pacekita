@extends('layouts.app')
@section('title', 'Run together, go further')
@section('content')
<section class="hero"><div class="shell hero-grid">
    <div class="hero-copy"><div class="hero-label"><span class="pulse"></span> YOUR NEXT RUN STARTS HERE</div><h1>Find your<br><em>kind of pace.</em></h1><p>Good runs are better together. Find local crews, explore upcoming sessions, and show up as you are.</p><div class="hero-actions"><a class="button button-lime" href="{{ route('runs.index') }}">Explore runs <span>↗</span></a><a class="button button-outline" href="{{ route('clubs.index') }}">Find a club</a></div><div class="hero-foot"><span>01 / 03</span><span>YOUR CITY. YOUR CREW. YOUR PACE.</span></div></div>
    <div class="hero-art" aria-hidden="true"><div class="orbit orbit-one"></div><div class="orbit orbit-two"></div><div class="art-track"><div class="art-center">MOVE<br><span>TOGETHER</span><i>↗</i></div></div><span class="art-label art-label-one">START HERE ↗</span><span class="art-label art-label-two">KEEP GOING ↗</span><span class="art-number">26°</span></div>
</div></section>
<section class="ticker" aria-label="PaceKita values"><div class="shell">RUN YOUR WAY <span>✳</span> FIND YOUR PEOPLE <span>✳</span> SHOW UP TOGETHER <span>✳</span> RUN YOUR WAY</div></section>
<section class="section shell"><div class="section-head"><div><span class="kicker">THE NEXT START LINE</span><h2>Runs worth<br><em>showing up for.</em></h2></div><a class="link-arrow" href="{{ route('runs.index') }}">All upcoming runs ↗</a></div>
    @if($upcoming->isEmpty())<div class="empty"><h3>First run coming soon.</h3><p>No public runs published yet. Explore clubs or start your own crew.</p><a class="button button-dark" href="{{ route('clubs.index') }}">Explore clubs ↗</a></div>
    @else <div class="card-grid">@foreach($upcoming as $occurrence)<x-run-card :occurrence="$occurrence" />@endforeach</div> @endif
</section>
<section class="feature-band"><div class="shell feature-grid"><div><span class="kicker">MORE THAN MILES</span><h2>Same streets.<br><em>New people.</em></h2></div><div><p>Whether it’s your first 3K or your fiftieth half marathon, there’s a place for you here. No pressure. Just people moving forward.</p><a class="button button-lime" href="{{ route('clubs.index') }}">Meet the clubs ↗</a></div></div></section>
<section class="section shell steps"><span class="kicker">HOW IT WORKS</span><h2>Three steps to<br><em>your next run.</em></h2><div class="steps-grid"><div><span>01</span><h3>Find your fit</h3><p>Filter by city, date, distance and pace. There’s a run for your kind of day.</p></div><div><span>02</span><h3>Save your spot</h3><p>RSVP in a tap. If it’s full, join the waitlist and we’ll keep you posted.</p></div><div><span>03</span><h3>Show up together</h3><p>Meet your crew at the start line. The rest is yours to make.</p></div></div></section>
@endsection
