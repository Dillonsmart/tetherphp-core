<?php

declare(strict_types=1);

namespace TetherPHP\framework\Traits;

/**
 * Where an ADR triple goes, and what shape each operation of one has.
 *
 * Every feature gets a directory. `make:feature Blog` and `make:resource Post`
 * write the same layout — the resource just writes seven operations into it
 * instead of one:
 *
 *     app/Actions/Blog/Index.php               Actions\Blog\Index
 *     app/Domains/Blog/Index.php               Domains\Blog\Index
 *     app/Domains/Blog/Results/Page.php        Domains\Blog\Results\Page
 *     app/Responders/Blog/Index.php            Responders\Blog\Index
 *     app/Views/pages/blog/index.php
 *
 * Actions, Domains and Responders are named for the operation; a Result is
 * named for its shape, and shared by every operation of the feature that
 * answers the same way. A resource has seven of the first three and three
 * Results: `Collection` for the list, `Record` for the one, `Written` for the
 * three that change something and answer with a redirect.
 *
 * Features used to be flat — `app/Actions/Blog.php` — and resources nested,
 * which was two layouts for one concept. It was also a dead end: a feature that
 * gained a second route meant moving four files and rewriting four namespaces.
 * A feature becomes a resource by addition now.
 *
 * The views already worked this way. `make:feature Blog` has always written
 * `app/Views/pages/blog/index.php`; only the classes disagreed.
 *
 * @phpstan-type OperationSpec array{
 *     verb: string,
 *     route: string,
 *     domain: string,
 *     domainProperties: string,
 *     domainArguments: string,
 *     result: string,
 *     resultArguments: string,
 *     responder: string,
 *     page: string,
 *     view: string,
 *     redirect: string,
 *     todo: string
 * }
 */
trait GeneratesTriples
{
    use GeneratesFiles;

    /**
     * One page, with nothing read off the request.
     *
     * What `make:feature` writes, and what the piecemeal generators write for
     * any operation they are given. `make:resource` is the only command that
     * uses the CRUD shapes below, so what lands on disk stays predictable from
     * the command name alone.
     *
     * @return OperationSpec
     */
    protected function pageOperation(string $feature, string $operation, string $viewName, string $page): array
    {
        return [
            'verb' => 'get',
            'route' => '/' . $viewName,
            'domain' => 'Domain',
            'domainProperties' => '',
            'domainArguments' => '',
            'result' => 'ResultPage',
            'resultArguments' => "'{$feature}'",
            'responder' => 'ResponderPage',
            'page' => $page,
            'view' => 'ViewPage',
            'redirect' => '',
            'todo' => 'return what this page needs.',
        ];
    }

    /**
     * The seven operations of a CRUD resource, and what makes each one
     * different from the others.
     *
     * This table is the whole of `make:resource`. Everything else is the same
     * six steps `writeStub()` already does, so what a reader needs in order to
     * predict what lands on disk is here in one place: which stub, what the
     * Action hands the Domain, what the Domain hands the Result, and where a
     * write redirects to afterwards.
     *
     * @return array<string, OperationSpec>
     */
    protected function resourceOperations(string $uri): array
    {
        $id = '        private readonly string $id,';
        $payload = "        /** @var array<string, mixed> */\n        private readonly array \$payload,";

        $fromPath = '$request->params[\'id\'] ?? \'\'';
        $fromBody = '$request->payload';

        return [
            'Index' => [
                'verb' => 'get',
                'route' => $uri,
                'domain' => 'Domain',
                'domainProperties' => '',
                'domainArguments' => '',
                'result' => 'ResultCollection',
                'resultArguments' => '[]',
                'responder' => 'ResponderCollection',
                'page' => 'index',
                'view' => 'ViewIndex',
                'redirect' => '',
                'todo' => 'read the records to list.',
            ],
            'Create' => [
                'verb' => 'get',
                'route' => $uri . '/create',
                'domain' => 'Domain',
                'domainProperties' => '',
                'domainArguments' => '',
                'result' => 'ResultRecord',
                'resultArguments' => "'', []",
                'responder' => 'ResponderRecord',
                'page' => 'create',
                'view' => 'ViewForm',
                'redirect' => '',
                'todo' => 'return the empty attributes a new record starts with.',
            ],
            'Store' => [
                'verb' => 'post',
                'route' => $uri,
                'domain' => 'DomainWithInput',
                'domainProperties' => $payload,
                'domainArguments' => $fromBody,
                'result' => 'ResultWritten',
                'resultArguments' => "''",
                'responder' => 'ResponderRedirect',
                'page' => '',
                'view' => '',
                'redirect' => "'{$uri}/' . \$result->id",
                'todo' => 'create a record from $this->payload and return its identifier.',
            ],
            'Show' => [
                'verb' => 'get',
                'route' => $uri . '/{id}',
                'domain' => 'DomainWithInput',
                'domainProperties' => $id,
                'domainArguments' => $fromPath,
                'result' => 'ResultRecord',
                'resultArguments' => '$this->id, []',
                'responder' => 'ResponderRecord',
                'page' => 'show',
                'view' => 'ViewShow',
                'redirect' => '',
                'todo' => 'find the record identified by $this->id, or throw HttpNotFoundException.',
            ],
            'Edit' => [
                'verb' => 'get',
                'route' => $uri . '/{id}/edit',
                'domain' => 'DomainWithInput',
                'domainProperties' => $id,
                'domainArguments' => $fromPath,
                'result' => 'ResultRecord',
                'resultArguments' => '$this->id, []',
                'responder' => 'ResponderRecord',
                'page' => 'edit',
                'view' => 'ViewForm',
                'redirect' => '',
                'todo' => 'find the record identified by $this->id, or throw HttpNotFoundException.',
            ],
            'Update' => [
                'verb' => 'put',
                'route' => $uri . '/{id}',
                'domain' => 'DomainWithInput',
                'domainProperties' => $id . "\n" . $payload,
                'domainArguments' => $fromPath . ', ' . $fromBody,
                'result' => 'ResultWritten',
                'resultArguments' => '$this->id',
                'responder' => 'ResponderRedirect',
                'page' => '',
                'view' => '',
                'redirect' => "'{$uri}/' . \$result->id",
                'todo' => 'apply $this->payload to the record identified by $this->id.',
            ],
            'Destroy' => [
                'verb' => 'delete',
                'route' => $uri . '/{id}',
                'domain' => 'DomainWithInput',
                'domainProperties' => $id,
                'domainArguments' => $fromPath,
                'result' => 'ResultWritten',
                'resultArguments' => '$this->id',
                'responder' => 'ResponderRedirect',
                'page' => '',
                'view' => '',
                'redirect' => "'{$uri}'",
                'todo' => 'delete the record identified by $this->id.',
            ],
        ];
    }

    /**
     * @param OperationSpec $spec
     *
     * @return array<string, string> placeholder => value, without the braces
     */
    protected function replacements(string $feature, string $operation, string $viewName, string $uri, array $spec): array
    {
        return [
            'feature' => $feature,
            'operation' => $operation,
            'result' => $this->resultShape($spec['result']),
            'viewName' => $viewName,
            'uri' => $uri,
            'page' => $spec['page'],
            'todo' => $spec['todo'],
            'domainArguments' => $spec['domainArguments'],
            'domainProperties' => $spec['domainProperties'],
            'resultArguments' => $spec['resultArguments'],
            'redirect' => $spec['redirect'],
        ];
    }

    /**
     * The class a result stub writes, which is the stub's own name without the
     * `Result` prefix: `ResultCollection.txt` renders `Results\Collection`.
     *
     * Results are named for their shape rather than for the operation, because
     * there are only three answers a CRUD domain gives — many, one, or "I
     * changed this" — and there were seven classes carrying them. Show and Edit
     * returned classes that differed by their name and nothing else, which is
     * the duplication Principle 4 exists to reject.
     */
    protected function resultShape(string $stub): string
    {
        return str_starts_with($stub, 'Result') ? substr($stub, strlen('Result')) : $stub;
    }

    /**
     * Result, Domain, Responder, Action, and the view if the operation renders
     * one — in the order the classes name one another. A Domain's return type
     * names its Result, a Responder names it too, and the Action names all three.
     *
     * @param OperationSpec $spec
     *
     * @return int one of the COMMAND_* constants
     */
    protected function writeTriple(string $feature, string $operation, string $viewName, string $uri, array $spec): int
    {
        $status = $this->writeDomain($feature, $operation, $viewName, $uri, $spec);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        $status = $this->writeResponder($feature, $operation, $viewName, $uri, $spec);

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        return $this->writeAction($feature, $operation, $viewName, $uri, $spec);
    }

    /**
     * The Result comes with the Domain, always: `handle()` is typed to return
     * it, so a Domain generated without one does not load. They are one unit of
     * work even though they are two files, which is why there is no make:result.
     *
     * @param OperationSpec $spec
     *
     * @return int one of the COMMAND_* constants
     */
    protected function writeDomain(string $feature, string $operation, string $viewName, string $uri, array $spec): int
    {
        $replacements = $this->replacements($feature, $operation, $viewName, $uri, $spec);

        // shared by every operation of this feature that answers the same way,
        // so a second one finding it already written is expected, not a failure
        $status = $this->writeSharedStub(
            $spec['result'],
            app_dir() . "/Domains/{$feature}/Results/{$this->resultShape($spec['result'])}.php",
            $replacements,
        );

        if ($status !== self::COMMAND_SUCCESS) {
            return $status;
        }

        return $this->writeStub($spec['domain'], app_dir() . "/Domains/{$feature}/{$operation}.php", $replacements);
    }

    /**
     * The view comes with the Responder for the same reason, where there is one
     * — a Responder rendering a template that does not exist throws
     * "View not found" on first request. A write redirects and has no view.
     *
     * @param OperationSpec $spec
     *
     * @return int one of the COMMAND_* constants
     */
    protected function writeResponder(string $feature, string $operation, string $viewName, string $uri, array $spec): int
    {
        $replacements = $this->replacements($feature, $operation, $viewName, $uri, $spec);

        $status = $this->writeStub(
            $spec['responder'],
            app_dir() . "/Responders/{$feature}/{$operation}.php",
            $replacements,
        );

        if ($status !== self::COMMAND_SUCCESS || $spec['view'] === '') {
            return $status;
        }

        return $this->writeStub(
            $spec['view'],
            app_dir() . "/Views/pages/{$viewName}/{$spec['page']}.php",
            $replacements,
        );
    }

    /**
     * @param OperationSpec $spec
     *
     * @return int one of the COMMAND_* constants
     */
    protected function writeAction(string $feature, string $operation, string $viewName, string $uri, array $spec): int
    {
        return $this->writeStub(
            'Action',
            app_dir() . "/Actions/{$feature}/{$operation}.php",
            $this->replacements($feature, $operation, $viewName, $uri, $spec),
        );
    }
}
