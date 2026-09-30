<input {{ $attributes->merge(['class' => 'input' . ((isset($errors) && $errors->has($name ?? $attributes->get('name') ?? '')) ? ' input-error' : '')]) }}>
