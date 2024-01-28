<?php

namespace WireUi\View;

use AllowDynamicProperties;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\{Arr, HtmlString, Str};
use Illuminate\View\Component;
use WireUi\Facades\WireUi;
use WireUi\Support\{ComponentPack};

/**
 * @method void mounted(array $data)
 * @method void processed(array $data)
 * @method void finished(array $data)
 */
#[AllowDynamicProperties]
abstract class WireUiComponent extends Component
{
    use ManageProps;

    public ?string $config = null;

    private array $setVariables = [];

    private array $smartAttributes = [];

    private function setConfig(): void
    {
        $this->config = WireUi::components()->resolveByAlias($this->componentName);
    }

    abstract protected function blade(): View;

    public function render(): Closure
    {
        return function (array $data) {
            return $this->blade()->with($this->runWireUiComponent($data));
        };
    }

    public function resolveView(): Closure|View
    {
        $view = $this->render();

        if ($view instanceof View) {
            return $view;
        }

        $resolver = fn (View $view) => new HtmlString($view->render());

        return fn (array $data = []) => $resolver($view($data));
    }

    private function runWireUiComponent(array $data): array
    {
        $this->setConfig();

        if (method_exists($this, 'mounted')) {
            $this->mounted($data);
        }

        foreach ($this->getMethods() as $method) {
            $this->{$method}($data);
        }

        if (method_exists($this, 'processed')) {
            $this->processed($data);
        }

        foreach ($this->setVariables as $attribute) {
            $data[$attribute] = $this->{$attribute};
        }

        $data['attributes'] = $this->attributes->except($this->smartAttributes);

        return tap($data, function (array &$data) {
            if (method_exists($this, 'finished')) {
                $this->finished($data);
            }
        });
    }

    private function getMethods(): array
    {
        $methods = collect(get_class_methods($this))->filter(
            fn ($method) => Str::startsWith($method, 'setup'),
        )->values();

        if ($methods->contains('setupSize')) {
            $methods = $methods->reject('setupSize')->prepend('setupSize');
        }

        if ($methods->contains('setupColor')) {
            $methods = $methods->reject('setupColor')->push('setupColor');
        }

        if ($methods->contains('setupStateColor')) {
            $methods = $methods->reject('setupStateColor')->push('setupStateColor');
        }

        return $methods->values()->toArray();
    }

    protected function getData(string $attribute, mixed $default = null): mixed
    {
        if ($this->attributes->has($kebab = Str::kebab($attribute))) {
            $this->smartAttributes($kebab);

            return $this->attributes->get($kebab);
        }

        if ($this->attributes->has($camel = Str::camel($attribute))) {
            $this->smartAttributes($camel);

            return $this->attributes->get($camel);
        }

        if ($kebab === 'icon-size' && property_exists($this, 'size')) {
            return $this->size;
        }

        return config("wireui.{$this->config}.default.{$kebab}") ?? $default;
    }

    protected function getDataModifier(string $attribute, ComponentPack $dataPack): mixed
    {
        $value = $this->attributes->get($attribute) ?? $this->getMatchModifier($dataPack->keys());

        $remove = in_array($value, $dataPack->keys()) ? [$value] : [];

        $this->smartAttributes([$attribute, ...$remove]);

        return $value ?? config("wireui.{$this->config}.default.{$attribute}");
    }

    protected function setVariables(mixed $variables): void
    {
        collect(Arr::wrap($variables))->filter()->each(
            fn ($value) => $this->setVariables[] = $value,
        );
    }

    protected function smartAttributes(mixed $attributes): void
    {
        collect(Arr::wrap($attributes))->filter()->each(
            fn ($value) => $this->smartAttributes[] = $value,
        );
    }

    protected function getMatchModifier(array $keys): ?string
    {
        return array_key_first($this->attributes->only($keys)->getAttributes());
    }

    protected function useValidation(): bool
    {
        return property_exists($this, 'useValidationColors') && $this->useValidationColors;
    }
}
