<?php

namespace EXSyst\Component\XML;

use EXSyst\Component\IO\Source\StringSource;
use EXSyst\Component\IO\Reader\StringCDataReader;

class DOMUtils
{
    public static function outerHTML(\DOMNode $node = null)
    {
        if ($node === null) {
            return '';
        }
        $doc = new \DOMDocument();
        $doc->appendChild($doc->importNode($node, true));

        return $doc->saveHTML();
    }

    public static function innerHTML(\DOMNode $node = null)
    {
        if ($node === null) {
            return '';
        }
        $doc = $node->ownerDocument;
        $html = [];
        foreach ($node->childNodes as $child) {
            $html[] = $doc->saveHTML($child);
        }

        return implode('', $html);
    }

    public static function getElementsByClassName(\DOMNode $node = null, $className, $maxLevel = null)
    {
        return self::getElementsBy($node, self::_classNameTest($className), $maxLevel);
    }

    public static function getFirstElementByClassName(\DOMNode $node = null, $className, $maxLevel = null)
    {
        return self::getFirstElementBy($node, self::_classNameTest($className), $maxLevel);
    }

    public static function getElementsByClassNameIter(\DOMNode $node = null, $className, $maxLevel = null)
    {
        return self::getElementsByIter($node, self::_classNameTest($className), $maxLevel);
    }

    public static function getElementsByNodeName(\DOMNode $node = null, $nodeName, $maxLevel = null)
    {
        return self::getElementsBy($node, self::_nodeNameTest($nodeName), $maxLevel);
    }

    public static function getFirstElementByNodeName(\DOMNode $node = null, $nodeName, $maxLevel = null)
    {
        return self::getFirstElementBy($node, self::_nodeNameTest($nodeName), $maxLevel);
    }

    public static function getElementsByNodeNameIter(\DOMNode $node = null, $nodeName, $maxLevel = null)
    {
        return self::getElementsByIter($node, self::_nodeNameTest($nodeName), $maxLevel);
    }

    public static function getElementById(\DOMNode $node = null, $id, $maxLevel = null)
    {
        return self::getFirstElementBy($node, self::_idTest($nodeName), $maxLevel);
    }

    public static function getElementsBy(\DOMNode $node = null, $by, $maxLevel = null)
    {
        return iterator_to_array(self::getElementsByIter($node, $by, $maxLevel));
    }

    public static function getFirstElementBy(\DOMNode $node = null, $by, $maxLevel = null)
    {
        foreach (self::getElementsByIter($node, $by, $maxLevel) as $node) {
            return $node;
        }

        return;
    }

    public static function getElementsByIter(\DOMNode $node = null, $by, $maxLevel = null)
    {
        if ($maxLevel !== null && $maxLevel <= 0) {
            return;
        }
        $children = $node->childNodes;
        if (!$children) {
            return;
        }
        $n = $children->length;
        for ($i = 0; $i < $n; ++$i) {
            $child = $children->item($i);
            if ($by($child)) {
                yield $child;
            }
            foreach (self::getElementsByIter($child, $by, ($maxLevel === null) ? null : ($maxLevel - 1)) as $descendant) {
                yield $descendant;
            }
        }
    }

    public static function selectFirstNode(\DOMNode $node = null, $selector, array $functions = [])
    {
        foreach (self::selectNodesIter($node, $selector, $functions) as $node) {
            return $node;
        }

        return;
    }

    public static function selectNodes(\DOMNode $node = null, $selector, array $functions = [])
    {
        return iterator_to_array(self::selectNodesIter($node, $selector, $functions));
    }

    public static function selectNodesIter(\DOMNode $node = null, $selector, array $functions = [])
    {
        list($maxLevel, $by, $remaining) = self::_compileSelector($selector, $functions);
        if (isset($remaining)) {
            foreach (self::getElementsByIter($node, $by, $maxLevel) as $node) {
                foreach (self::selectNodesIter($node, $remaining, $functions) as $descendant) {
                    yield $descendant;
                }
            }
        } else {
            foreach (self::getElementsByIter($node, $by, $maxLevel) as $node) {
                yield $node;
            }
        }
    }

    public static function matches(\DOMNode $node = null, $selector, array $functions = [])
    {
        $by = self::compileSimpleSelector($node, $selector, $functions);

        return $by($node);
    }

    public static function compileSimpleSelector($selector, array $functions = [])
    {
        list($maxLevel, $by, $remaining) = self::_compileSelector($selector, $functions);
        if (isset($maxLevel) || isset($remaining)) {
            throw new \Exception('Complex selectors are not allowed here');
        }

        return $by;
    }

    public static function getClasses(\DOMElement $node = null)
    {
        if ($node === null) {
            return array();
        }

        return array_filter(explode(' ', $node->getAttribute('class')));
    }

    public static function hasClass(\DOMElement $node = null, $className)
    {
        if ($node === null) {
            return false;
        }

        return in_array($className, self::getClasses($node));
    }

    const SELECTOR_IDENTIFIER_SPAN = '-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz';
    const SELECTOR_IDENTIFIER_REGEX = '[0-9A-Za-z_-]+';

    private static $_std_functions;

    private static function _compileSelector($selector, array $functions)
    {
        if (is_array($selector)) {
            return $selector;
        }
        if (is_string($selector)) {
            $selector = trim($selector);
            if (preg_match('~^'.self::SELECTOR_IDENTIFIER_REGEX.'$~', $selector)) {
                return [null, self::_nodeNameTest($selector), null];
            } elseif (preg_match('~^#'.self::SELECTOR_IDENTIFIER_REGEX.'$~', $selector)) {
                return [null, self::_idTest(substr($selector, 1)), null];
            } elseif (preg_match('~^\\.'.self::SELECTOR_IDENTIFIER_REGEX.'$~', $selector)) {
                return [null, self::_classNameTest(substr($selector, 1)), null];
            }
        }
        if (!isset(self::$_std_functions)) {
            self::$_std_functions = self::_getStdFunctions();
        }
        if (is_string($selector) && preg_match('~^:'.self::SELECTOR_IDENTIFIER_REGEX.'$~', $selector)) {
            $function = substr($selector, 1);
            if (isset($functions[$function])) {
                $factory = $functions[$function];
            } elseif (isset(self::$_std_functions[$function])) {
                $factory = self::$_std_functions[$function];
            } else {
                throw new Exception('Unknown selector function');
            }

            return [null, $factory($function, null, null, $functions), null];
        }
        if (!($selector instanceof StringCDataReader)) {
            $selector = new StringCDataReader(new StringSource($selector));
        }
        if ($selector->eat('>')) {
            $maxLevel = 1;
            $selector->eatWhiteSpace();
        } else {
            $maxLevel = null;
        }
        $predicate = null;
        if ($selector->eat('*')) {
        } elseif (strlen($nodeName = $selector->eatSpan(self::SELECTOR_IDENTIFIER_SPAN))) {
            $predicate = self::_nodeNameTest($nodeName, $predicate);
        }
        for (;;) {
            if ($selector->eat('#')) {
                $predicate = self::_idTest($selector->eatSpan(self::SELECTOR_IDENTIFIER_SPAN), $predicate);
            } elseif ($selector->eat('.')) {
                $predicate = self::_classNameTest($selector->eatSpan(self::SELECTOR_IDENTIFIER_SPAN), $predicate);
            } elseif ($selector->eat(':')) {
                $function = $selector->eatSpan(self::SELECTOR_IDENTIFIER_SPAN);
                if (!isset($functions[$function]) && !isset(self::$_std_functions[$function])) {
                    throw new \Exception('Unknown selector function');
                }
                if ($selector->eat('(')) {
                    $paren = 1;
                    $param = '';
                    for (;;) {
                        $param .= $selector->eatCSpan('()');
                        if ($selector->eat('(')) {
                            $param .= '(';
                            ++$paren;
                        } elseif ($selector->eat(')')) {
                            if (--$paren > 0) {
                                $param .= ')';
                            } else {
                                break;
                            }
                        } else {
                            throw new Exception('Invalid selector');
                        }
                    }
                } else {
                    $param = null;
                }
                $factory = isset($functions[$function]) ? $functions[$function] : self::$_std_functions[$function];
                $predicate = $factory($function, $param, $predicate, $functions);
            } elseif ($selector->isFullyConsumed() || $selector->eatWhiteSpace() || $selector->peek(1) == '>') {
                break;
            } else {
                throw new \Exception('Invalid selector');
            }
        }
        if ($predicate === null) {
            $predicate = function () { return true; };
        }

        return [$maxLevel, $predicate, $selector->isFullyConsumed() ? null : self::_compileSelector($selector, $functions)];
    }

    private static function _getStdFunctions()
    {
        return [
            'not' => function ($_, $selector, $and, $functions) {
                if ($selector === null) {
                    throw new \Exception('Missing parameter');
                }
                $by = self::compileSimpleSelector($selector, $functions);
                if ($and) {
                    return function ($child) use ($by, $and) {
                        return $and($child) && !$by($child);
                    };
                } else {
                    return function ($child) use ($by) {
                        return !$by($child);
                    };
                }
            },
            'has' => function ($_, $selector, $and, $functions) {
                if ($selector === null) {
                    throw new Exception('Missing parameter');
                }
                $selector = self::_compileSelector($selector, $functions);
                if ($and) {
                    return function ($child) use ($selector, $functions, $and) {
                        return $and($child) && self::selectFirstNode($child, $selector, $functions) !== null;
                    };
                } else {
                    return function ($child) use ($selector, $functions) {
                        return self::selectFirstNode($child, $selector, $functions) !== null;
                    };
                }
            },
            'first' => function ($_, $__, $and) {
                $first = true;
                if ($and) {
                    return function ($child) use (&$first, $and) {
                        if (!$and($child)) {
                            return false;
                        }
                        if ($first) {
                            $first = false;

                            return true;
                        } else {
                            return false;
                        }
                    };
                } else {
                    return function () use (&$first) {
                        if ($first) {
                            $first = false;

                            return true;
                        } else {
                            return false;
                        }
                    };
                }

            },
        ];
    }

    private static function _nodeNameTest($nodeName, $and = null)
    {
        $nodeName = strtolower($nodeName);
        if ($and) {
            return function ($child) use ($nodeName, $and) {
                return $and($child) && strtolower($child->nodeName) == $nodeName;
            };
        } else {
            return function ($child) use ($nodeName) {
                return strtolower($child->nodeName) == $nodeName;
            };
        }
    }

    private static function _idTest($id, $and = null)
    {
        if ($and) {
            return function ($child) use ($id, $and) {
                return $and($child) && $child instanceof \DOMElement && $child->getAttribute('id') === $id;
            };
        } else {
            return function ($child) use ($id) {
                return $child instanceof \DOMElement && $child->getAttribute('id') === $id;
            };
        }
    }

    private static function _classNameTest($className, $and = null)
    {
        if ($and) {
            return function ($child) use ($className, $and) {
                return $and($child) && $child instanceof \DOMElement && self::hasClass($child, $className);
            };
        } else {
            return function ($child) use ($className) {
                return $child instanceof \DOMElement && self::hasClass($child, $className);
            };
        }
    }
}
