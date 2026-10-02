@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="font-display text-2xl font-bold text-blue-navy">{{ $title }}</h1>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
