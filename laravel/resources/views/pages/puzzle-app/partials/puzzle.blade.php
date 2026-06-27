@php
    $colPx = 38;
    $loesLetters = $grid['loesWord'] !== '' ? \App\Services\WordNormalizer::chars($grid['loesWord']) : [];
@endphp
<div class="puzzle-render lang-{{ $puzzle['language'] ?? 'de' }}" dir="ltr" :class="{ 'show-solution': showSolution }">
    <div class="puzzle-title-bar">
        <h2>{{ $puzzle['title'] ?? '' }}</h2>
        <span class="puzzle-number-badge">Nr.&nbsp;{{ $puzzleNumber }}</span>
        <span class="puzzle-dedication">{{ $name ? 'Für '.$name : 'Mit lieben Grüßen' }}</span>
    </div>

    <div class="crossword-layout">
        <div class="grid-column">
            <div class="crossword-grid" dir="ltr" style="grid-template-columns: repeat({{ $grid['gw'] }}, {{ $colPx }}px); grid-template-rows: repeat({{ $grid['gh'] }}, {{ $colPx }}px)">
                @foreach($grid['cells'] as $row)
                    @foreach($row as $cell)
                        @if($cell['black'])
                            <div class="cell black"></div>
                        @else
                            <div class="cell">
                                @if($cell['num'])<span class="cell-num">{{ $cell['num'] }}</span>@endif
                                <span class="cell-letter hidden">{{ $cell['letter'] }}</span>
                                @if($cell['loes'] !== null)<span class="loes-zahl">{{ $cell['loes'] }}</span>@endif
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>

        @if($grid['loesWord'] !== '')
        <div class="loeswort-unter-gitte">
            <div class="loeswort-leiste-head">{{ $grid['loesLabel'] }}</div>
            <div class="loeswort-slots">
                @for($i = 0; $i < $grid['loesLen']; $i++)
                    <div class="loeswort-slot" x-text="showSolution ? (loesLetters[{{ $i }}] || '') : ''"></div>
                @endfor
            </div>
            <p class="loeswort-hint">{{ $grid['loesHint'] }}</p>
        </div>
        @endif

        <div class="clues-below @if($grid['rtl']) clues-rtl @endif">
            <div class="clues-section">
                <div class="clues-heading">{{ $grid['acrossLabel'] }}</div>
                @foreach($grid['across'] as $w)
                    <div class="clue-item">
                        <span class="clue-num">{{ $w['number'] }}</span>
                        <span class="clue-text">{{ $w['clue'] }}</span>
                        <span class="clue-answer" x-show="showSolution" x-cloak>{{ $w['word'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
            <div class="clues-section">
                <div class="clues-heading">{{ $grid['downLabel'] }}</div>
                @foreach($grid['down'] as $w)
                    <div class="clue-item">
                        <span class="clue-num">{{ $w['number'] }}</span>
                        <span class="clue-text">{{ $w['clue'] }}</span>
                        <span class="clue-answer" x-show="showSolution" x-cloak>{{ $w['word'] ?? '' }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
