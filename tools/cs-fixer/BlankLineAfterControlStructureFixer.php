<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tools\CsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;

/**
 * Ensures a blank line after the closing brace of a control structure
 * (`if`, `for`, `foreach`, `while`, `do`, `switch`, `try`) when the next
 * line is another statement. Complements `blank_line_before_statement`,
 * which only fires before specific keywords — this rule also separates
 * blocks from following plain expressions and assignments.
 */
final class BlankLineAfterControlStructureFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Noeka/blank_line_after_control_structure';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Ensures a blank line after the closing brace of `if`/`for`/`foreach`/`while`/`do`/`switch`/`try` blocks when followed by another statement.',
            [
                new CodeSample("<?php\nif (\$a) {\n    foo();\n}\nbar();\n"),
            ],
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isAnyTokenKindsFound([T_IF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_TRY]);
    }

    public function getPriority(): int
    {
        // Run after blank_line_before_statement (-19) so the two rules
        // converge on the same final whitespace.
        return -20;
    }

    protected function applyFix(\SplFileInfo $file, Tokens $tokens): void
    {
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!$token->isGivenKind([T_IF, T_FOR, T_FOREACH, T_WHILE, T_DO, T_SWITCH, T_TRY])) {
                continue;
            }

            // `while` after `}` is the tail of a do-while — skip; the matching
            // `T_DO` handles it.
            if ($token->isGivenKind(T_WHILE) && $this->isDoWhileTail($tokens, $i)) {
                continue;
            }

            $endIndex = $this->findStructureEnd($tokens, $i);

            if ($endIndex === null) {
                continue;
            }

            $this->ensureBlankLineAfter($tokens, $endIndex);
        }
    }

    private function isDoWhileTail(Tokens $tokens, int $whileIndex): bool
    {
        $prev = $tokens->getPrevMeaningfulToken($whileIndex);

        return $prev !== null && $tokens[$prev]->equals('}');
    }

    /**
     * Returns the index of the last token of the control structure starting
     * at $index — the closing `}` (or the `;` for `do … while(…);`),
     * skipping over `elseif`/`else`/`catch`/`finally` continuations.
     */
    private function findStructureEnd(Tokens $tokens, int $index): ?int
    {
        if ($tokens[$index]->isGivenKind(T_DO)) {
            return $this->findDoWhileEnd($tokens, $index);
        }

        $blockEnd = $this->findBlockEndAfterCondition($tokens, $index);

        if ($blockEnd === null) {
            return null;
        }

        while (true) {
            $after = $tokens->getNextMeaningfulToken($blockEnd);

            if ($after === null) {
                break;
            }

            if (!$tokens[$after]->isGivenKind([T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY])) {
                break;
            }

            $next = $this->findBlockEndAfterCondition($tokens, $after);

            if ($next === null) {
                break;
            }

            $blockEnd = $next;
        }

        return $blockEnd;
    }

    /**
     * Given the index of a control-structure keyword (or continuation
     * keyword), skip its optional `(…)` condition and return the index of
     * the closing `}` of its `{ … }` body.
     */
    private function findBlockEndAfterCondition(Tokens $tokens, int $keywordIndex): ?int
    {
        $cursor = $tokens->getNextMeaningfulToken($keywordIndex);

        if ($cursor === null) {
            return null;
        }

        if ($tokens[$cursor]->equals('(')) {
            $closeParen = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $cursor);
            $cursor = $tokens->getNextMeaningfulToken($closeParen);
        }

        if ($cursor === null || !$tokens[$cursor]->equals('{')) {
            // Alternative syntax (`: … endif;`) or single-statement bodies
            // — outside the scope of this fixer.
            return null;
        }

        return $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $cursor);
    }

    private function findDoWhileEnd(Tokens $tokens, int $doIndex): ?int
    {
        $openBrace = $tokens->getNextTokenOfKind($doIndex, ['{']);

        if ($openBrace === null) {
            return null;
        }

        $closeBrace = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_CURLY_BRACE, $openBrace);
        $while = $tokens->getNextMeaningfulToken($closeBrace);

        if ($while === null || !$tokens[$while]->isGivenKind(T_WHILE)) {
            return null;
        }

        $openParen = $tokens->getNextMeaningfulToken($while);

        if ($openParen === null || !$tokens[$openParen]->equals('(')) {
            return null;
        }

        $closeParen = $tokens->findBlockEnd(Tokens::BLOCK_TYPE_PARENTHESIS_BRACE, $openParen);
        $semicolon = $tokens->getNextMeaningfulToken($closeParen);

        if ($semicolon === null || !$tokens[$semicolon]->equals(';')) {
            return null;
        }

        return $semicolon;
    }

    private function ensureBlankLineAfter(Tokens $tokens, int $endIndex): void
    {
        $wsIndex = $endIndex + 1;

        if (!isset($tokens[$wsIndex]) || !$tokens[$wsIndex]->isWhitespace()) {
            return;
        }

        $followIndex = $wsIndex + 1;

        if (!isset($tokens[$followIndex])) {
            return;
        }

        $follow = $tokens[$followIndex];

        // No work if the next thing closes the enclosing block, continues
        // the structure, or is a comment we shouldn't displace.
        if ($follow->equals('}') || $follow->equals(';')) {
            return;
        }

        if ($follow->isGivenKind([T_ELSE, T_ELSEIF, T_CATCH, T_FINALLY])) {
            return;
        }

        if ($follow->isComment()) {
            return;
        }

        $content = $tokens[$wsIndex]->getContent();
        $newlineCount = substr_count($content, "\n");

        // Same line — leave alone. Already blank-line separated — done.
        if ($newlineCount === 0 || $newlineCount >= 2) {
            return;
        }

        $lastNewlinePos = strrpos($content, "\n");
        $indent = $lastNewlinePos !== false ? substr($content, $lastNewlinePos + 1) : '';

        $tokens[$wsIndex] = new Token([T_WHITESPACE, "\n\n" . $indent]);
    }
}
