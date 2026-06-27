@php
    $placement = $placement ?? 'page';
@endphp
<div class="rk-memory">
    <h2 class="rk-section-title">{{ $this->t('memTitle') }}</h2>
    <p class="rk-hint rk-memory-intro">{{ $this->t('memIntro') }}</p>

    @if($this->isPflegeheim && !$selectedPatientId)
        <p class="rk-empty-note">{{ $this->t('memSelectPatient') }}</p>
    @else
        <div class="rk-memory-body rk-memory-body--wide">
            <div class="mem-section">
                <div class="mem-section-title">{{ $this->t('memOverview') }}</div>
                <div class="mem-stats">
                    <span class="mem-stat-chip">
                        <strong>{{ $memory['puzzleCount'] }}</strong> {{ $this->t('memPuzzlesSaved') }}
                    </span>
                    <span class="mem-stat-chip">
                        <strong>{{ $memory['uniqueWords'] }}</strong> {{ $this->t('memUniqueWords') }}
                    </span>
                </div>
                <div class="mem-actions">
                    <button type="button" class="rk-btn rk-btn--sm" wire:click="copyWordList" @if(!$memory['wordFreq']) disabled @endif>
                        {{ $this->t('memCopyText') }}
                    </button>
                    <button type="button" class="rk-btn rk-btn--sm rk-btn--danger"
                            wire:click="clearScopedMemory"
                            wire:confirm="{{ $this->t('memDeleteAll') }}?"
                            @if(!$memory['puzzleCount']) disabled @endif>
                        {{ $this->t('memDeleteAll') }}
                    </button>
                </div>
            </div>

            <div class="mem-section mem-section--freq">
                <div class="mem-section-title">{{ $this->t('memFreqTitle') }}</div>
                @if($memory['wordFreq'])
                    <div class="word-freq-grid">
                        <div class="wf-head">{{ $this->t('memWordCol') }}</div>
                        <div class="wf-head wf-head--center">{{ $this->t('memCountCol') }}</div>
                        <div class="wf-head"></div>
                        @foreach($memory['wordFreq'] as $row)
                            <div class="wf-cell">{{ $row['word'] }}</div>
                            <div class="wf-cell wf-cell--center">
                                <span class="wf-count-badge">{{ $row['count'] }}</span>
                            </div>
                            <div class="wf-cell"></div>
                        @endforeach
                    </div>
                @else
                    <p class="rk-empty-note">{{ $this->t('memEmpty') }}</p>
                @endif
            </div>

            <div class="mem-section mem-section--history">
                <div class="mem-section-title">{{ $this->t('memHistoryTitle') }}</div>
                @if($memory['entries'])
                    <div class="rk-list rk-memory-history">
                        @foreach($memory['entries'] as $entry)
                            <div class="rk-list-item">
                                <div class="grow">
                                    <div class="title">{{ $entry['title'] ?: '—' }}</div>
                                    <div class="meta">{{ $entry['created_at'] }} · {{ $this->t('histWordCount', $entry['word_count']) }}</div>
                                </div>
                                <button type="button" class="rk-btn rk-btn--sm" wire:click="openHistoryItem({{ $entry['id'] }})">{{ $this->t('btnLoad') }}</button>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="rk-empty-note">{{ $this->t('historyEmpty') }}</p>
                @endif
            </div>
        </div>
    @endif
</div>
