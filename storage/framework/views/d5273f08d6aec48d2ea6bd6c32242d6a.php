<?php echo $__env->make('wayfinder::docblock', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo when($shouldExport, 'export '); ?>const <?php echo $method; ?> = (<?php echo $__env->make('wayfinder::function-arguments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>): RouteDefinition<<?php echo \Illuminate\Support\Js::from($verbs->first()->actual)->toHtml() ?>> => ({
    url: <?php echo $method; ?>.url(<?php echo when($parameters->isNotEmpty(), $args . ', '); ?><?php echo $options; ?>),
    method: <?php echo \Illuminate\Support\Js::from($verbs->first()->actual)->toHtml() ?>,
})

<?php echo $method; ?>.definition = {
    methods: <?php echo $verbs->pluck('actual')->toJson(); ?>,
    url: <?php echo $uri; ?>,
} satisfies RouteDefinition<<?php echo $verbs->pluck('actual')->toJson(); ?>>

<?php echo $__env->make('wayfinder::docblock', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $method; ?>.url = (<?php echo $__env->make('wayfinder::function-arguments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>) => {
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameters->count() === 1): ?>
    if (typeof <?php echo $args; ?> === 'string' || typeof <?php echo $args; ?> === 'number') {
        <?php echo $args; ?> = { <?php echo $parameters->first()->name; ?>: <?php echo $args; ?> }
    }

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameters->first()->key): ?>
        if (typeof <?php echo $args; ?> === 'object' && !Array.isArray(<?php echo $args; ?>) && <?php echo \Illuminate\Support\Js::from($parameters->first()->key)->toHtml() ?> in <?php echo $args; ?>) {
            <?php echo $args; ?> = { <?php echo $parameters->first()->name; ?>: <?php echo $args; ?>.<?php echo $parameters->first()->key; ?> }
        }
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameters->isNotEmpty()): ?>
    if (Array.isArray(<?php echo $args; ?>)) {
        <?php echo $args; ?> = {
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $parameters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            <?php echo $parameter->name; ?>: <?php echo $args; ?>[<?php echo $loop->index; ?>],
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        }
    }

    <?php echo $args; ?> = applyUrlDefaults(<?php echo $args; ?>)
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameters->where('optional')->isNotEmpty()): ?>
    validateParameters(<?php echo $args; ?>, [
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $parameters->where('optional'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        "<?php echo $parameter->name; ?>",
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    ])
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameters->isNotEmpty()): ?>
    const <?php echo $parsedArgs; ?> = {
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $parameters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameter->key): ?>
            <?php echo $parameter->name; ?>: <?php echo when($parameter->default !== null, '('); ?>typeof <?php echo $args; ?><?php echo when($parameters->every->optional, '?'); ?>.<?php echo $parameter->name; ?> === 'object'
                ? <?php echo $args; ?>.<?php echo $parameter->name; ?>.<?php echo $parameter->key ?? 'id'; ?>

                : <?php echo $args; ?><?php echo when($parameters->every->optional, '?'); ?>.<?php echo $parameter->name; ?><?php echo when($parameter->default !== null, ') ?? '); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameter->default !== null): ?><?php echo \Illuminate\Support\Js::from($parameter->default)->toHtml() ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>,
        <?php else: ?>
            <?php echo $parameter->name; ?>: <?php echo $args; ?><?php echo when($parameters->every->optional, '?'); ?>.<?php echo $parameter->name; ?><?php echo when($parameter->default !== null, ' ?? '); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($parameter->default !== null): ?><?php echo \Illuminate\Support\Js::from($parameter->default)->toHtml() ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>,
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
    }
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    return <?php echo $method; ?>.definition.url
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $parameters; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $parameter): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
            .replace(<?php echo \Illuminate\Support\Js::from($parameter->placeholder)->toHtml() ?>, <?php echo $parsedArgs; ?>.<?php echo $parameter->name; ?><?php echo when($parameter->optional, '?'); ?>.toString()<?php echo when($parameter->optional, " ?? ''"); ?>)
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last): ?>
            .replace(/\/+$/, '')
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?> + queryParams(<?php echo $options; ?>)
}

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $verbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $verb): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
<?php echo $__env->make('wayfinder::docblock', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $method; ?>.<?php echo $verb->actual; ?> = (<?php echo $__env->make('wayfinder::function-arguments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>): RouteDefinition<<?php echo \Illuminate\Support\Js::from($verb->actual)->toHtml() ?>> => ({
    url: <?php echo $method; ?>.url(<?php echo when($parameters->isNotEmpty(), $args . ', '); ?><?php echo $options; ?>),
    method: <?php echo \Illuminate\Support\Js::from($verb->actual)->toHtml() ?>,
})
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($withForm): ?>
    <?php echo $__env->make('wayfinder::docblock', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    const <?php echo $method; ?>Form = (<?php echo $__env->make('wayfinder::function-arguments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>): RouteFormDefinition<<?php echo \Illuminate\Support\Js::from($verbs->first()->formSafe)->toHtml() ?>> => ({
        action: <?php echo $method; ?>.url(
            <?php echo when($parameters->isNotEmpty(), $args . ', '); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($verbs->first()->formSafe === $verbs->first()->actual): ?>
                <?php echo $options; ?>

            <?php else: ?>
                {
                    [<?php echo $options; ?>?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: <?php echo \Illuminate\Support\Js::from(strtoupper($verbs->first()->actual))->toHtml() ?>,
                        ...(<?php echo $options; ?>?.query ?? <?php echo $options; ?>?.mergeQuery ?? {}),
                    }
                }
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        ),
        method: <?php echo \Illuminate\Support\Js::from($verbs->first()->formSafe)->toHtml() ?>,
    })

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $verbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $verb): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoopIteration(); ?><?php endif; ?>
        <?php echo $__env->make('wayfinder::docblock', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $method; ?>Form.<?php echo $verb->actual; ?> = (<?php echo $__env->make('wayfinder::function-arguments', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>): RouteFormDefinition<<?php echo \Illuminate\Support\Js::from($verb->formSafe)->toHtml() ?>> => ({
            action: <?php echo $method; ?>.url(
                <?php echo when($parameters->isNotEmpty(), $args . ', '); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($verb->formSafe === $verb->actual): ?>
                <?php echo $options; ?>

                <?php else: ?>
                    {
                        [<?php echo $options; ?>?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: <?php echo \Illuminate\Support\Js::from(strtoupper($verb->actual))->toHtml() ?>,
                            ...(<?php echo $options; ?>?.query ?? <?php echo $options; ?>?.mergeQuery ?? {}),
                        }
                    }
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            ),
            method: <?php echo \Illuminate\Support\Js::from($verb->formSafe)->toHtml() ?>,
        })
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>

    <?php echo $method; ?>.form = <?php echo $method; ?>Form
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH /Users/ilxomjonabdullayev/Public/Projects/statups/open-sales/pos-desktop/vendor/laravel/wayfinder/src/../resources/method.blade.ts ENDPATH**/ ?>