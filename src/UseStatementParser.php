<?php

/**
 * This file is part of the Miny framework.
 * (c) Dániel Buga <daniel@bugadani.hu>
 *
 * For licensing information see the LICENSE file.
 */

namespace Modules\Annotation;

class UseStatementParser
{
    private $namespace;
    private $position;

    public function __construct($source)
    {
        $this->tokens = token_get_all($source);
    }

    public function getNamespace()
    {
        if (!isset($this->namespace)) {
            $this->position = count($this->tokens) - 1;
            while (!$this->match(T_NAMESPACE)) {
                --$this->position;
                if (!$this->valid()) {
                    $this->namespace = '\\';

                    return $this->namespace;
                }
            }
            $this->skip(T_WHITESPACE, T_NS_SEPARATOR);
            $this->namespace = $this->parseClassName();
        }

        return $this->namespace;
    }

    public function getImports()
    {
        $imports = [];
        $this->getNamespace();

        $first = true;
        while ($this->valid()) {
            //find first use
            while (!$this->match(T_USE) && ($first || !$this->match(','))) {
                $this->step();
                if (!$this->valid()) {
                    break 2;
                }
            }
            $first = false;

            $this->skip(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_NS_SEPARATOR);

            $fqn       = $this->parseClassName();
            $shortName = $this->tokens[ $this->position - 1 ][1];

            $this->skip(T_WHITESPACE);
            if ($this->match(T_AS) && $this->match(T_WHITESPACE)) {
                $shortName = $this->getCurrentToken();
            }
            $imports[ $shortName ] = $fqn;
        }

        return $imports;
    }

    private function skip()
    {
        $tokens = func_get_args();
        while (in_array($this->tokens[ $this->position ][0], $tokens)) {
            $this->step();
        }
    }

    /**
     * @param $token
     * @return bool
     */
    private function match($token)
    {
        if (!$this->valid()) {
            return false;
        }
        $current = $this->tokens[ $this->position ];
        if ($current === $token) {
            $this->step();

            return true;
        }
        if (!is_array($current)) {
            return false;
        }
        if ($current[0] === $token) {
            $this->step();

            return true;
        }

        return false;
    }

    private function step()
    {
        ++$this->position;
    }

    /**
     * @return mixed
     */
    private function getCurrentToken()
    {
        return $this->tokens[ $this->position++ ][1];
    }

    /**
     * @return bool
     */
    private function valid()
    {
        return isset($this->tokens[ $this->position ]);
    }

    private function parseClassName()
    {
        $name = $this->getCurrentToken();
        while ($this->match(T_NS_SEPARATOR)) {
            $name .= '\\' . $this->getCurrentToken();
        }

        return $name;
    }
}
