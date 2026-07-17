@error($name)
    {{-- Bootstrap dipakai bertahap; halaman lama tetap memakai class error sebelumnya. --}}
    <div class="{{ ($bootstrap ?? false) ? 'invalid-feedback' : 'travs-field-error' }}">{{ $message }}</div>
@enderror
