@php
    $langs = [
        'de' => 'Deutsch', 'es' => 'Español', 'it' => 'Italiano',
        'tr' => 'Türkçe', 'ru' => 'Русский', 'el' => 'Ελληνικά', 'ar' => 'العربية',
    ];
@endphp

<div class="rk-app lang-{{ $language }}" @if($this->isRtl) dir="rtl" @endif>

    {{-- ───────────── Header ───────────── --}}
    <header class="rk-header">
        <div class="rk-brand-wrap">
            <div class="rk-brand">{{ config('app.name') }}</div>
            <div class="rk-brand-sub">{{ $this->t('brandSubtitle') }}</div>
        </div>
        <div class="rk-header-spacer"></div>
        <div class="rk-header-actions">
            <select class="rk-lang" wire:model.live="language" title="Sprache / Language">
                @foreach($langs as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
            @auth
                <span class="rk-user">{{ $this->currentUser->name }}</span>
                <button type="button" class="rk-btn rk-btn--sm" wire:click="logout">{{ $this->t('btnLogout') }}</button>
            @endauth
        </div>
    </header>

    {{-- ════════════ GUEST: Landing + Login ════════════ --}}
    @guest
    <main class="rk-landing">
        <div class="rk-landing-copy">
            <p class="rk-landing-eyebrow">{{ $this->t('landingEyebrow') }}</p>
            <h1 class="rk-landing-title">{{ config('app.name') }}</h1>
            <p class="rk-landing-tagline">Kreuzworträtsel <em>{{ $this->t('landingCursive') }}</em></p>
            <p class="rk-landing-lede">{{ $this->t('landingDesc') }}</p>

            <ol class="rk-landing-steps">
                <li>
                    <span class="rk-landing-step-num">1</span>
                    <div>
                        <strong>{{ $this->t('landingStep1Title') }}</strong>
                        <p>{{ $this->t('landingStep1Desc') }}</p>
                    </div>
                </li>
                <li>
                    <span class="rk-landing-step-num">2</span>
                    <div>
                        <strong>{{ $this->t('landingStep2Title') }}</strong>
                        <p>{{ $this->t('landingStep2Desc') }}</p>
                    </div>
                </li>
                <li>
                    <span class="rk-landing-step-num">3</span>
                    <div>
                        <strong>{{ $this->t('landingStep3Title') }}</strong>
                        <p>{{ $this->t('landingStep3Desc') }}</p>
                    </div>
                </li>
            </ol>
        </div>

        <div class="rk-card rk-auth-card">
            <div class="rk-auth-tabs">
                <button type="button" class="rk-auth-tab @if($authMode==='login') active @endif" wire:click="$set('authMode','login')">{{ $this->t('btnLogin') }}</button>
                <button type="button" class="rk-auth-tab @if($authMode==='register') active @endif" wire:click="$set('authMode','register')">{{ $this->t('btnRegister') }}</button>
            </div>

            @if($authMode === 'register')
            <div class="rk-field">
                <label class="rk-label">{{ $this->t('labelYourName') }}</label>
                <input class="rk-input" type="text" wire:model="authName" placeholder="{{ $this->t('placeholderAuthName') }}">
                @error('authName') <p class="rk-form-error">{{ $message }}</p> @enderror
            </div>
            @endif

            <div class="rk-field">
                <label class="rk-label">{{ $this->t('labelEmail') }}</label>
                <input class="rk-input" type="email" wire:model="authEmail" placeholder="{{ $this->t('placeholderAuthEmail') }}">
                @error('authEmail') <p class="rk-form-error">{{ $message }}</p> @enderror
            </div>

            <div class="rk-field">
                <label class="rk-label">{{ $this->t('labelPassword') }}</label>
                <input class="rk-input" type="password" wire:model="authPassword" placeholder="{{ $this->t('placeholderAuthPassword') }}" wire:keydown.enter="{{ $authMode === 'login' ? 'login' : 'register' }}">
                @error('authPassword') <p class="rk-form-error">{{ $message }}</p> @enderror
            </div>

            @if($authMode === 'register')
            <div class="rk-checkrow">
                <input type="checkbox" id="chkPflegeheim" wire:model="authIsPflegeheim">
                <label for="chkPflegeheim">{{ $this->t('labelIsPflegeheim') }}</label>
            </div>
            <p class="rk-hint">{{ $this->t('hintPflegeheim') }}</p>
            @endif

            <button type="button" class="rk-btn rk-btn--primary rk-btn--block" style="margin-top:16px" wire:click="{{ $authMode === 'login' ? 'login' : 'register' }}">
                {{ $authMode === 'login' ? $this->t('btnLogin') : $this->t('btnRegister') }}
            </button>
        </div>
    </main>

    {{-- ════════════ AUTH: App ════════════ --}}
    @else

    <nav class="rk-tabs no-print">
        <button type="button" class="rk-tab @if($activePanel==='create') active @endif" wire:click="setPanel('create')">{{ $this->t('tabPuzzle') }}</button>
        <button type="button" class="rk-tab @if($activePanel==='history') active @endif" wire:click="setPanel('history')">{{ $this->t($this->isPflegeheim ? 'tabHistoryOrg' : 'tabHistory') }}</button>
        @if($this->isPflegeheim)
            <button type="button" class="rk-tab @if($activePanel==='patients') active @endif" wire:click="setPanel('patients')">{{ $this->t('btnManagePatients') }}</button>
        @endif
    </nav>

    <main class="rk-main">

    @if($activePanel === 'create')
        <div class="rk-studio">
            <div class="rk-studio-col rk-studio-col--form">
            @if($mode === 'zip')
            <form class="rk-card rk-form no-print" method="POST" action="{{ route('puzzle.export.zip') }}">
                @csrf
                <input type="hidden" name="zipCount" value="{{ $zipCount }}">
            @else
            <form class="rk-card rk-form no-print" wire:submit.prevent="generate">
            @endif

                <fieldset class="rk-fieldset">
                    <legend class="rk-legend"><span class="n">1</span> {{ $this->t('labelForPatient') ?: 'Für wen?' }}</legend>
                    @if($this->isPflegeheim)
                    <div class="rk-field">
                        <label class="rk-label">{{ $this->t('labelSelectPatient') }}</label>
                        <select class="rk-select" wire:model.live="selectedPatientId">
                            <option value="">{{ $this->t('optionNoPatient') }}</option>
                            @foreach($patients as $pt)
                                <option value="{{ $pt['id'] }}">{{ $pt['name'] }}</option>
                            @endforeach
                        </select>
                        @if($selectedPatientId)
                            <button type="button" class="rk-btn rk-btn--link rk-patient-edit-link" wire:click="editPatient({{ $selectedPatientId }})">
                                {{ $this->t('btnEditPatient') }} →
                            </button>
                        @endif
                    </div>
                    @endif
                    <div class="rk-field">
                        <label class="rk-label">{{ $this->t('labelName') }}</label>
                        <input class="rk-input" type="text" wire:model.blur="name" placeholder="{{ $this->t('placeholderName') }}">
                    </div>
                </fieldset>

                <fieldset class="rk-fieldset rk-fieldset--core">
                    <legend class="rk-legend rk-legend--core">
                        <span class="n">2</span>
                        {{ $this->t('labelFamily') }}
                    </legend>
                    <p class="rk-family-lede">{{ $this->t('familyCoreLede') }}</p>
                    <div class="rk-field">
                        <textarea class="rk-textarea rk-textarea--family"
                                  wire:model.blur="familyStory"
                                  rows="9"
                                  placeholder="{{ $this->t('placeholderFamily') }}"></textarea>
                    </div>
                    <div class="rk-checkrow rk-checkrow--family">
                        <input type="checkbox" id="chkUseFamily" wire:model="useFamilyStory">
                        <label for="chkUseFamily">{{ $this->t('labelUseFamily') }}</label>
                    </div>
                    <details class="rk-family-extra">
                        <summary aria-label="{{ $this->t('labelContext') }}"><span class="rk-family-extra-toggle" aria-hidden="true">+</span></summary>
                        <div class="rk-field">
                            <label class="rk-label">{{ $this->t('labelContext') }}</label>
                            <textarea class="rk-textarea" wire:model.blur="customContext" placeholder="{{ $this->t('placeholderContext') }}"></textarea>
                        </div>
                    </details>
                </fieldset>

                <fieldset class="rk-fieldset">
                    <legend class="rk-legend"><span class="n">3</span> {{ $this->t('labelLanguage') }}, {{ $this->t('labelDifficulty') }}</legend>
                    <div class="rk-field">
                        <label class="rk-label">{{ $this->t('labelPuzzleLanguage') }}</label>
                        <select class="rk-select" wire:model="puzzleLanguage">
                            @foreach($langs as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rk-row-2">
                        <div class="rk-field">
                            <label class="rk-label">{{ $this->t('labelDifficulty') }}</label>
                            <select class="rk-select" wire:model="difficulty">
                                <option value="sehr_leicht">{{ $this->t('diffSehrLeicht') }}</option>
                                <option value="leicht">{{ $this->t('diffLeicht') }}</option>
                                <option value="mittel">{{ $this->t('diffMittel') }}</option>
                            </select>
                        </div>
                        <div class="rk-field">
                            <label class="rk-label">{{ $this->t('labelWordCount') }}</label>
                            <select class="rk-select" wire:model="wordCount">
                                <option value="16">{{ $this->t('wc16') }}</option>
                                <option value="20">{{ $this->t('wc20') }}</option>
                                <option value="24">{{ $this->t('wc24') }}</option>
                                <option value="28">{{ $this->t('wc28') }}</option>
                                <option value="32">{{ $this->t('wc32') }}</option>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="rk-fieldset">
                    <legend class="rk-legend"><span class="n">4</span> {{ $this->t('topicsTitle') }}</legend>
                    <p class="rk-hint" style="margin-top:-4px;margin-bottom:10px">{{ $this->t('topicsLede') }}</p>
                    <div class="rk-chips">
                        @foreach($this->topics as $topic)
                            <button type="button"
                                class="rk-chip @if(in_array($topic['id'], $selectedTopics, true)) selected @endif"
                                wire:click="toggleTopic('{{ $topic['id'] }}')">
                                {{ $topic['label'] }}
                            </button>
                        @endforeach
                    </div>
                </fieldset>

                <div class="rk-generate">
                    <div class="rk-segment">
                        <button type="button" class="@if($mode==='single') active @endif" wire:click="$set('mode','single')">{{ $this->t('modeSingle') }}</button>
                        <button type="button" class="@if($mode==='zip') active @endif" wire:click="$set('mode','zip')">{{ $this->t('modeZip') }}</button>
                        @if($mode === 'zip')
                        <span class="rk-zip-inline">
                            <input class="rk-input" type="number" min="1" max="15" wire:model="zipCount">
                        </span>
                        @endif
                    </div>

                    <button type="submit" class="rk-btn rk-btn--primary rk-btn--lg rk-btn--block"
                            @if($mode !== 'zip') wire:loading.attr="disabled" wire:target="generate" @endif>
                        @if($mode === 'zip')
                            {{ $this->t('btnDeckZip') }}
                        @else
                            <span wire:loading.remove wire:target="generate">{{ $this->t('btnGenerate') }}</span>
                            <span wire:loading wire:target="generate">{{ $this->t('statusHint') }} …</span>
                        @endif
                    </button>
                </div>
            </form>
            </div>

            <div class="rk-studio-col rk-studio-col--result">
            <section class="rk-card rk-result @if(!$grid && $this->showMemory && $status !== 'error') rk-result--memory @endif">
                <div wire:loading.flex wire:target="generate" class="rk-loading">
                    <div class="rk-spinner"></div>
                    <p>Rätsel wird erstellt …</p>
                </div>

                <div wire:loading.remove wire:target="generate" style="display:flex;flex-direction:column;flex:1">
                    @if($status === 'error')
                        <div class="rk-error">
                            <h3>{{ $this->t('errorTitle') }}</h3>
                            <code>{{ $errorMessage }}</code>
                            <p style="margin-top:12px">{{ $this->t('errorRetry') }}</p>
                        </div>
                    @elseif($grid)
                        @php
                            $loesLetters = $grid['loesWord'] !== '' ? \App\Services\WordNormalizer::chars($grid['loesWord']) : [];
                        @endphp
                        <div wire:key="puzzle-{{ $puzzleNumber }}"
                             x-data="{ showSolution: false, loesLetters: @js($loesLetters) }"
                             x-effect="document.body.classList.toggle('show-solution', showSolution)"
                             style="display:flex;flex-direction:column;flex:1">
                            <div class="rk-result-scroll">
                                @include('pages.puzzle-app.partials.puzzle', ['grid' => $grid])
                            </div>
                            <div class="rk-result-actions no-print">
                                <button type="button"
                                        class="rk-btn"
                                        :class="{ 'rk-btn--primary': showSolution }"
                                        @click="showSolution = !showSolution">
                                    <span x-show="!showSolution">{{ $this->t('btnToggleSolution') }}</span>
                                    <span x-show="showSolution" x-cloak>{{ $this->t('btnHideSolution') }}</span>
                                </button>
                                <a class="rk-btn rk-btn--primary" href="{{ route('puzzle.export.pdf') }}" download>{{ $this->t('btnPdf') }}</a>
                                <a class="rk-btn" href="{{ route('puzzle.export.pdf', ['solution' => 1]) }}" download>{{ $this->t('btnPdfSolution') }}</a>
                                <a class="rk-btn" href="{{ route('puzzle.export.png') }}" download>{{ $this->t('btnPng') }}</a>
                            </div>
                        </div>
                    @elseif($this->showMemory)
                        @include('pages.puzzle-app.partials.memory', ['memory' => $memory, 'placement' => 'page'])
                    @elseif($this->isPflegeheim && !$selectedPatientId)
                        <div class="rk-empty">
                            <p class="rk-hint">{{ $this->t('memSelectPatient') }}</p>
                        </div>
                    @else
                        <div class="rk-empty">
                            <h3>{{ $this->t('welcomeTitle') }}</h3>
                        </div>
                    @endif
                </div>
            </section>
            </div>
        </div>

    @elseif($activePanel === 'history')
        <div class="rk-page-grid rk-page-grid--single">
        <div class="rk-card rk-page-panel">
            <h2 class="rk-section-title">{{ $this->t($this->isPflegeheim ? 'tabHistoryOrg' : 'tabHistory') }}</h2>
            @if($this->isPflegeheim && $selectedPatientId && $name)
                <p class="rk-hint" style="margin-bottom:14px">{{ $this->t('labelForPatient') }} <strong>{{ $name }}</strong></p>
            @endif
            @if(count($history))
            <div class="rk-list">
                @foreach($history as $h)
                <div class="rk-list-item">
                    <div class="grow">
                        <div class="title">{{ $h['title'] ?: '—' }}</div>
                        <div class="meta">{{ $h['created_at'] }} · {{ strtoupper($h['language']) }}</div>
                    </div>
                    <button type="button" class="rk-btn rk-btn--sm" wire:click="openHistoryItem({{ $h['id'] }})">{{ $this->t('btnLoad') }}</button>
                    <button type="button" class="rk-btn rk-btn--sm rk-btn--danger" wire:click="deleteHistoryItem({{ $h['id'] }})" wire:confirm="Eintrag löschen?">×</button>
                </div>
                @endforeach
            </div>
            @else
                <p class="rk-empty-note">{{ $this->t('historyEmpty') }}</p>
            @endif
        </div>
        </div>

    @elseif($activePanel === 'patients' && $this->isPflegeheim)
        <div class="rk-page-grid rk-page-grid--single">
        <div class="rk-card rk-page-panel">
            <h2 class="rk-section-title">{{ $this->t('btnManagePatients') }}</h2>

            @if($editingPatientId)
            <div class="rk-patient-edit rk-fieldset--core">
                <h3 class="rk-patient-edit-title">{{ $this->t('patientEditTitle') }} — {{ $name }}</h3>
                <p class="rk-family-lede">{{ $this->t('patientEditLede') }}</p>

                <div class="rk-field">
                    <label class="rk-label">{{ $this->t('labelPatientName') }}</label>
                    <input class="rk-input" type="text" wire:model.blur="name" placeholder="{{ $this->t('placeholderPatientName') }}">
                </div>
                @error('name') <p class="rk-form-error">{{ $message }}</p> @enderror

                <div class="rk-field">
                    <label class="rk-label">{{ $this->t('labelFamily') }}</label>
                    <textarea class="rk-textarea rk-textarea--family"
                              wire:model.blur="familyStory"
                              rows="9"
                              placeholder="{{ $this->t('placeholderFamily') }}"></textarea>
                </div>

                <div class="rk-field">
                    <label class="rk-label">{{ $this->t('labelContext') }}</label>
                    <textarea class="rk-textarea" wire:model.blur="customContext" placeholder="{{ $this->t('placeholderContext') }}"></textarea>
                </div>

                <div class="rk-row-2">
                    <div class="rk-field">
                        <label class="rk-label">{{ $this->t('labelLanguage') }}</label>
                        <select class="rk-select" wire:model="puzzleLanguage">
                            @foreach($langs as $code => $label)
                                <option value="{{ $code }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="rk-field">
                        <label class="rk-label">{{ $this->t('labelDifficulty') }}</label>
                        <select class="rk-select" wire:model="difficulty">
                            <option value="sehr_leicht">{{ $this->t('diffSehrLeicht') }}</option>
                            <option value="leicht">{{ $this->t('diffLeicht') }}</option>
                            <option value="mittel">{{ $this->t('diffMittel') }}</option>
                        </select>
                    </div>
                </div>

                @if($patientSaveMessage)
                    <p class="rk-save-ok">{{ $patientSaveMessage }}</p>
                @endif

                <div class="rk-patient-edit-actions">
                    <button type="button" class="rk-btn rk-btn--primary" wire:click="savePatientProfile">{{ $this->t('btnSave') }}</button>
                    <button type="button" class="rk-btn" wire:click="cancelPatientEdit">{{ $this->t('btnCancel') }}</button>
                </div>
            </div>
            @else
            <div class="rk-add-row">
                <input class="rk-input" type="text" wire:model="newPatientName" placeholder="{{ $this->t('placeholderPatientName') }}" wire:keydown.enter="createPatient">
                <button type="button" class="rk-btn rk-btn--primary" wire:click="createPatient">{{ $this->t('btnAddPatient') }}</button>
            </div>
            @error('newPatientName') <p class="rk-form-error">{{ $message }}</p> @enderror

            @if(count($patients))
            <div class="rk-list">
                @foreach($patients as $pt)
                <div class="rk-list-item @if($selectedPatientId === $pt['id']) selected @endif">
                    <div class="grow">
                        <div class="title">{{ $pt['name'] }}</div>
                        <div class="meta">{{ strtoupper($pt['language']) }} · {{ $pt['difficulty'] }}</div>
                        <div class="meta rk-patient-story-meta">
                            @if($pt['has_story'])
                                {{ $this->t('patientHasStory', $pt['story_chars']) }}
                            @else
                                {{ $this->t('patientNoStory') }}
                            @endif
                        </div>
                    </div>
                    <button type="button" class="rk-btn rk-btn--sm rk-btn--primary" wire:click="editPatient({{ $pt['id'] }})">{{ $this->t('btnEditPatient') }}</button>
                    <button type="button" class="rk-btn rk-btn--sm rk-btn--danger" wire:click="deletePatient({{ $pt['id'] }})" wire:confirm="Bewohner wirklich löschen?">×</button>
                </div>
                @endforeach
            </div>
            @else
                <p class="rk-empty-note">{{ $this->t('patientsEmpty') }}</p>
            @endif
            @endif
        </div>
        </div>
    @endif

    </main>
    @endguest

    <footer class="rk-footer no-print">
        {{ $name ? $this->t('footerNamedFamily', $name) : $this->t('footerFamily') }}
    </footer>
</div>
