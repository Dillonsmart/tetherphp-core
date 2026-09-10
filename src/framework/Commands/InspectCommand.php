<?php

declare(strict_types=1);

namespace TetherPHP\framework\Commands;

use TetherPHP\framework\Interfaces\ActionInterface;
use TetherPHP\framework\Interfaces\DomainResult;
use TetherPHP\framework\Interfaces\ResponderInterface;
use TetherPHP\framework\Traits\InspectsApplication;

class InspectCommand extends Command
{
    use InspectsApplication;

    public string $command = 'inspect';

    public string $description = 'Say what a class is in ADR terms and what it depends on';

    /** @var array<string, string> */
    protected array $arguments = [
        'class' => 'A class name, short (Home) or fully qualified (Actions\Home)',
    ];

    /**
     * Where a short name is looked for, in order.
     *
     * An application says `Home` and means one of five things depending on
     * which directory it is in. Guessing in a fixed order and *reporting the
     * name that was resolved* beats making the reader type the namespace.
     *
     * @var list<string>
     */
    private const array NAMESPACES = ['Actions\\', 'Domains\\', 'Domains\\Results\\', 'Responders\\', 'Commands\\'];

    public function execute(): int
    {
        $name = $this->argument('class');

        if ($name === '') {
            $this->error('A class is required, e.g. tether inspect Home');

            return self::COMMAND_INVALID_ARGUMENT;
        }

        $class = $this->resolve($name);

        if ($class === null) {
            $this->error("Could not find a class named '{$name}'.");
            $this->line();
            $this->line('Looked for it as given, and under: ' . implode(', ', self::NAMESPACES));
            $this->line('An autoloadable class needs a psr-4 mapping in composer.json.');

            return self::COMMAND_ERROR;
        }

        $reflection = new \ReflectionClass($class);

        $this->info($class);
        $this->line('Role      ' . $this->role($class));

        $file = $this->classFile($class);

        if ($file !== null) {
            $this->line("File      {$file}");
        }

        $parent = $reflection->getParentClass();

        if ($parent !== false) {
            $this->line("Extends   {$parent->getName()}");
        }

        $interfaces = $reflection->getInterfaceNames();

        if ($interfaces !== []) {
            $this->line('Implements ' . implode(', ', $interfaces));
        }

        $this->dependencies($reflection);

        if (is_subclass_of($class, ActionInterface::class)) {
            $this->triples($class);
        }

        return self::COMMAND_SUCCESS;
    }

    /**
     * @return class-string|null
     */
    private function resolve(string $name): ?string
    {
        if (class_exists($name)) {
            return $name;
        }

        foreach (self::NAMESPACES as $namespace) {
            if (class_exists($namespace . $name)) {
                return $namespace . $name;
            }
        }

        return null;
    }

    /**
     * @param class-string $class
     */
    private function role(string $class): string
    {
        return match (true) {
            is_subclass_of($class, ActionInterface::class) => 'Action — receives the Request, returns a Response',
            is_subclass_of($class, DomainResult::class) => 'Result — the value object a Domain returns',
            is_subclass_of($class, ResponderInterface::class) => 'Responder — turns a result into a Response',
            is_subclass_of($class, Command::class) => 'Command — console command',
            $this->looksLikeDomain($class) => 'Domain — business logic, no HTTP knowledge',
            default => 'not a TetherPHP role — no interface or base class identifies it',
        };
    }

    /**
     * A Domain has no interface to check.
     *
     * `Domains\Domain` is declared in the application, not the framework, so
     * core cannot name it — the framework must not `use` an application
     * namespace. What it can check is the shape the application's own base
     * class defines: a handle() that returns a DomainResult.
     */
    /**
     * @param class-string $class
     */
    private function looksLikeDomain(string $class): bool
    {
        $reflection = new \ReflectionClass($class);

        if (!$reflection->hasMethod('handle')) {
            return false;
        }

        $returns = $reflection->getMethod('handle')->getReturnType();

        return $returns instanceof \ReflectionNamedType
            && (is_subclass_of($returns->getName(), DomainResult::class) || $returns->getName() === DomainResult::class);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function dependencies(\ReflectionClass $reflection): void
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getParameters() === []) {
            $this->line('Takes     nothing');

            return;
        }

        $this->line('Takes');

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            $name = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

            $this->line("          \${$parameter->getName()}: {$name}");
        }
    }

    /**
     * @param class-string $action
     */
    private function triples(string $action): void
    {
        $this->line();
        $this->line('Its triple');

        foreach ($this->triple($action) as $role => $part) {
            if ($role === 'action') {
                continue;
            }

            $note = $part['exists'] ? '' : "  \033[31m(not found)\033[0m";

            // say which of these the code states and which this command guessed
            $note .= $part['declared'] ? '  (declared)' : '  (by convention)';

            $this->line(sprintf('          %-10s %s%s', $role, $part['class'], $note));
        }
    }
}
