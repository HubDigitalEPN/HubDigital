@props(['name', 'label' => null, 'description' => 'contraseña', 'placeholder' => '', 'autocomplete' => 'current-password'])

<flux:with-field :$attributes :$name :$label>
<div class="relative" x-data="{ visible: false }">
    <flux:input
        id="auth-{{ $name }}"
        :name="$name"
        type="password"
        x-bind:type="visible ? 'text' : 'password'"
        :placeholder="$placeholder"
        :autocomplete="$autocomplete"
        class:input="pe-12!"
        {{ $attributes }}
    />
    <button type="button"
        x-on:click="visible = !visible"
        x-bind:aria-label="visible ? 'Ocultar {{ $description }}' : 'Mostrar {{ $description }}'"
        x-bind:aria-pressed="visible.toString()"
        aria-label="Mostrar {{ $description }}"
        aria-pressed="false"
        aria-controls="auth-{{ $name }}"
        class="absolute inset-y-0 end-0 flex w-11 cursor-pointer items-center justify-center rounded-lg text-text-secondary hover:text-science-blue focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-science-blue">
        <span x-show="!visible"><flux:icon name="eye" class="size-5" /></span>
        <span x-show="visible" x-cloak><flux:icon name="eye-slash" class="size-5" /></span>
    </button>
</div>
</flux:with-field>
