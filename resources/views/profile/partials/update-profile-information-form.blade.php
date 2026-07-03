<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __("Update your account's profile information and KU affiliation.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($user->hasCompletedProfile())
            <div>
                <x-input-label value="Role" />
                <p class="mt-1 text-sm text-gray-700">{{ $user->role->label() }}</p>
            </div>

            <div>
                <x-input-label for="faculty" value="คณะ" />
                <select id="faculty" name="faculty" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">เลือกคณะ</option>
                    @foreach (config('ku_faculties.faculties', []) as $faculty)
                        <option value="{{ $faculty }}" @selected(old('faculty', $user->faculty) === $faculty)>{{ $faculty }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('faculty')" />
            </div>

            <div>
                <x-input-label for="department" value="สาขา / ภาควิชา" />
                <x-text-input id="department" name="department" type="text" class="mt-1 block w-full" :value="old('department', $user->department)" />
                <x-input-error class="mt-2" :messages="$errors->get('department')" />
            </div>

            @if ($user->role === \App\Enums\UserRole::Student)
                <div>
                    <x-input-label for="student_id" value="รหัสนิสิต" />
                    <x-text-input id="student_id" name="student_id" type="text" class="mt-1 block w-full" :value="old('student_id', $user->student_id)" />
                    <x-input-error class="mt-2" :messages="$errors->get('student_id')" />
                </div>
            @endif

            @if (in_array($user->role, [\App\Enums\UserRole::Researcher, \App\Enums\UserRole::Teacher], true))
                <div>
                    <x-input-label for="employee_id" value="รหัสพนักงาน" />
                    <x-text-input id="employee_id" name="employee_id" type="text" class="mt-1 block w-full" :value="old('employee_id', $user->employee_id)" />
                    <x-input-error class="mt-2" :messages="$errors->get('employee_id')" />
                </div>
            @endif

            @if ($user->role === \App\Enums\UserRole::Researcher)
                <div>
                    <x-input-label for="research_affiliation" value="หน่วยงาน / สังกัด" />
                    <x-text-input id="research_affiliation" name="research_affiliation" type="text" class="mt-1 block w-full" :value="old('research_affiliation', $user->research_affiliation)" />
                    <x-input-error class="mt-2" :messages="$errors->get('research_affiliation')" />
                </div>
            @endif
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
