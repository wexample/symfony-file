<?php

namespace Wexample\SymfonyFile\Service;

/**
 * Decides which paths a list of patterns takes, in the vocabulary of a
 * `.gitignore`.
 *
 * The rules are read in order and the last one to say something wins, so a line
 * opening on `!` gives back what the lines above it took. `**` crosses
 * directories, `*` stops at one, `?` is a single character, and a pattern
 * holding no `/` is about a name at any depth. A line opening on `/` is anchored
 * to the root, one ending on `/` is about directories alone, and `#` opens a
 * comment.
 *
 * Written here rather than in the browser because a selection is not only a way
 * of drawing a tree: the same rules have to answer what a mass action applies
 * to, and two implementations of a vocabulary this full of corners is two
 * implementations that disagree.
 */
final class PathMatcher
{
    /** @var array<int, array{negated: bool, directoryOnly: bool, segments: string[]}> */
    private array $rules;

    /**
     * @param string[] $patterns one rule per entry, in the order they were written
     */
    public function __construct(array $patterns)
    {
        $this->rules = array_values(array_filter(array_map(
            $this->parse(...),
            $patterns
        )));
    }

    /**
     * Whether the patterns take that path.
     *
     * Nothing said about it is nothing taken: a list of rules is a list of what
     * to keep, so what no rule names stays out.
     */
    public function matches(
        string $path,
        bool $isDirectory = false,
    ): bool {
        $segments = explode('/', $path);
        $taken = false;

        foreach ($this->rules as $rule) {
            if ($rule['directoryOnly'] && ! $isDirectory) {
                continue;
            }

            if ($this->fits($rule['segments'], $segments, false)) {
                $taken = ! $rule['negated'];
            }
        }

        return $taken;
    }

    /**
     * Whether anything below that directory could be taken.
     *
     * Read off the patterns and never off the disk: a rule that has consumed the
     * whole of a directory's path without running out of pattern can still match
     * something deeper, and one that cannot never will. This is what lets a tree
     * hide a branch it has never opened.
     */
    public function couldMatchUnder(string $directory): bool
    {
        $segments = '' === $directory ? [] : explode('/', $directory);
        $open = false;

        foreach ($this->rules as $rule) {
            // A later rule giving back a whole branch closes it, whatever the
            // rules above had opened — the order is what decides, here as
            // everywhere else.
            if ($rule['negated']) {
                $open = $open && ! $this->swallows($rule['segments'], $segments);

                continue;
            }

            $open = $open || $this->fits($rule['segments'], $segments, true);
        }

        return $open;
    }

    /**
     * Whether a rule covers everything below that directory, and not merely
     * something in it.
     *
     * Read on the shape of what is left of the rule once the directory has been
     * consumed: `**` and nothing else is the whole of the subtree, anything more
     * particular is only a part of it.
     *
     * @param string[] $pattern
     * @param string[] $segments
     */
    private function swallows(
        array $pattern,
        array $segments,
    ): bool {
        foreach ($segments as $segment) {
            if (! $pattern) {
                return false;
            }

            [$head] = $pattern;

            // A `**` standing where the directory still goes down covers the
            // rest of it, and stays for what follows.
            if ('**' !== $head) {
                if (! $this->fitsSegment($head, $segment)) {
                    return false;
                }

                $pattern = array_slice($pattern, 1);
            }
        }

        return ['**'] === $pattern;
    }

    /**
     * Whether a rule covers those segments.
     *
     * `$partial` asks the other question: not whether the rule matches what it
     * was given, but whether it is still running once it has been consumed —
     * which is what says something deeper may yet match.
     *
     * @param string[] $pattern
     * @param string[] $segments
     */
    private function fits(
        array $pattern,
        array $segments,
        bool $partial,
    ): bool {
        if (! $pattern) {
            return $partial ? false : ! $segments;
        }

        [$head] = $pattern;
        $rest = array_slice($pattern, 1);

        // `**` stands for any number of segments, so it is tried against each
        // remaining depth until one fits.
        if ('**' === $head) {
            // Everything below is still open to it, whatever comes after.
            if ($partial) {
                return true;
            }

            // A rule ending on `**` is about what lies inside, so it wants at
            // least one segment: `src/**` takes what is under `src` and not
            // `src` itself.
            if (! $rest) {
                return (bool) $segments;
            }

            for ($taken = 0; $taken <= count($segments); ++$taken) {
                if ($this->fits($rest, array_slice($segments, $taken), false)) {
                    return true;
                }
            }

            return false;
        }

        if (! $segments) {
            // The directory ran out before the rule did: what it still holds is
            // what could match deeper.
            return $partial;
        }

        return $this->fitsSegment($head, $segments[0])
            && $this->fits($rest, array_slice($segments, 1), $partial);
    }

    /** Whether one segment of a rule covers one segment of a path. */
    private function fitsSegment(
        string $pattern,
        string $segment,
    ): bool {
        return (bool) preg_match($this->expression($pattern), $segment);
    }

    private function expression(string $pattern): string
    {
        $expression = '';

        foreach (str_split($pattern) as $character) {
            $expression .= match ($character) {
                '*' => '[^/]*',
                '?' => '[^/]',
                default => preg_quote($character, '#'),
            };
        }

        return '#^'.$expression.'$#';
    }

    /**
     * One written line, as the rule it stands for, or null when it says nothing.
     *
     * @return array{negated: bool, directoryOnly: bool, segments: string[]}|null
     */
    private function parse(string $pattern): ?array
    {
        $pattern = trim($pattern);

        if ('' === $pattern || str_starts_with($pattern, '#')) {
            return null;
        }

        $negated = str_starts_with($pattern, '!');
        $pattern = $negated ? substr($pattern, 1) : $pattern;

        $directoryOnly = str_ends_with($pattern, '/');
        // Read before the slashes go: a leading one is what anchors a rule to
        // the root, and it cannot be told from the trimmed form.
        $anchored = str_starts_with($pattern, '/');
        $pattern = trim($pattern, '/');

        if ('' === $pattern) {
            return null;
        }

        $segments = explode('/', $pattern);

        // A rule naming one segment is about that name wherever it lies, which
        // is what `.gitignore` means by a pattern holding no slash.
        if (1 === count($segments) && ! $anchored) {
            $segments = ['**', $segments[0]];
        }

        return [
            'negated' => $negated,
            'directoryOnly' => $directoryOnly,
            'segments' => $segments,
        ];
    }
}
