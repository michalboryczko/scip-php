<?php

declare(strict_types=1);

namespace ScipPhp\Indexing;

use PhpParser\Node;
use Scip\Occurrence;
use Scip\SymbolInformation;
use Scip\SymbolRole;
use Scip\SyntaxKind;
use ScipPhp\Composer\Composer;
use ScipPhp\Parser\PosResolver;
use ScipPhp\SymbolNamer;

use function mb_check_encoding;
use function str_starts_with;

final class ScipReferenceEmitter
{
    public function __construct(
        private readonly IndexingContext $ctx,
        private readonly SymbolNamer $namer,
        private readonly Composer $composer,
    ) {
    }

    /**
     * Emit a SCIP reference occurrence, tracking external symbols as needed.
     *
     * @param  non-empty-string  $symbol
     */
    public function emitReference(
        PosResolver $pos,
        string $symbol,
        Node $posNode,
        int $kind = SyntaxKind::Identifier,
        int $role = SymbolRole::UnspecifiedSymbolRole,
    ): void {
        if (!mb_check_encoding($symbol, 'UTF-8')) {
            return;
        }
        if (!str_starts_with($symbol, 'local ')) {
            $ident = $this->namer->extractIdent($symbol);
            if ($this->composer->isDependency($ident)) {
                $this->ctx->extSymbols[$symbol] = new SymbolInformation([
                    'symbol'        => $symbol,
                    'documentation' => [], // TODO(drj): build hover content
                ]);
            }
        }

        $this->ctx->occurrences[] = new Occurrence([
            'range'        => $pos->pos($posNode),
            'symbol'       => $symbol,
            'symbol_roles' => $role,
            'syntax_kind'  => $kind,
        ]);
    }
}
