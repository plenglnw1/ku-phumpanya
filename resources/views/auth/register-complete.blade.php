<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Complete your profile</h1>
        <p class="mt-2 text-sm text-gray-600">
            Tell us your role and KU affiliation so we can personalize your experience.
        </p>
    </div>

    <form
        method="POST"
        action="{{ route('register.complete') }}"
        class="space-y-5"
        x-data="{ role: '{{ old('role', 'student') }}' }"
    >
        @csrf

        <div>
            <x-input-label for="name_display" value="Name" />
            <x-text-input id="name_display" class="mt-1 block w-full bg-gray-50" type="text" :value="$user->name" disabled />
        </div>

        <div>
            <x-input-label for="email_display" value="Email" />
            <x-text-input id="email_display" class="mt-1 block w-full bg-gray-50" type="email" :value="$user->email" disabled />
        </div>

        <div>
            <x-input-label for="role" value="Role / บทบาท" />
            <select
                id="role"
                name="role"
                x-model="role"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-phumpanya-900 focus:ring-phumpanya-900"
                required
            >
                <option value="student">นิสิต (Student)</option>
                <option value="researcher">นักวิจัย (Researcher)</option>
                <option value="teacher">อาจารย์ (Teacher)</option>
            </select>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="faculty" value="คณะ" />
            <select
                id="faculty"
                name="faculty"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-phumpanya-900 focus:ring-phumpanya-900"
                required
            >
                <option value="">เลือกคณะ</option>
                @foreach ($faculties as $faculty)
                    <option value="{{ $faculty }}" @selected(old('faculty') === $faculty)>{{ $faculty }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('faculty')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="department" value="สาขา / ภาควิชา" />
            <x-text-input id="department" name="department" class="mt-1 block w-full" type="text" :value="old('department')" required />
            <x-input-error :messages="$errors->get('department')" class="mt-2" />
        </div>

        <div x-show="role === 'student'" x-cloak>
            <x-input-label for="student_id" value="รหัสนิสิต (10 หลัก)" />
            <x-text-input id="student_id" name="student_id" class="mt-1 block w-full" type="text" inputmode="numeric" :value="old('student_id')" x-bind:required="role === 'student'" />
            <x-input-error :messages="$errors->get('student_id')" class="mt-2" />
        </div>

        <div x-show="role === 'researcher' || role === 'teacher'" x-cloak>
            <x-input-label for="employee_id" value="รหัสพนักงาน" />
            <x-text-input id="employee_id" name="employee_id" class="mt-1 block w-full" type="text" :value="old('employee_id')" x-bind:required="role === 'researcher' || role === 'teacher'" />
            <x-input-error :messages="$errors->get('employee_id')" class="mt-2" />
        </div>

        <div x-show="role === 'researcher'" x-cloak>
            <x-input-label for="research_affiliation" value="หน่วยงาน / สังกัด (ไม่บังคับ)" />
            <x-text-input id="research_affiliation" name="research_affiliation" class="mt-1 block w-full" type="text" :value="old('research_affiliation')" />
            <x-input-error :messages="$errors->get('research_affiliation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center">
            Save and continue
        </x-primary-button>
    </form>
</x-guest-layout>
