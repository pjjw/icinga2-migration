<?php

namespace Icinga\Module\Conftool\Icinga;

abstract class IcingaObjectDefinition
{
    protected $key = 'name';
    protected $props;
    protected $_parents = array();
    protected $is_template = false;
    protected $type;

    public function __construct($props)
    {
        $this->props = (object) array();
        foreach ($props as $k => $v) {
            $this->$k = $v;
        }
    }

    public function getProperties()
    {
        return $this->props;
    }

    public function getDefinitionType()
    {
        if ($this->type === null)
        {
            $class = preg_split('/\\\/', get_class($this));
            $class = end($class);
            $this->type = strtolower(
                substr($class, 6)
            );
        }
        return $this->type;
    }

    public function getAttributes()
    {
        $res = array();
        $key = $this->isTemplate() ? 'name' : $this->key;
        foreach ($this->props as $k => $v) {
            if ($key === $k) { continue; }
            if ($k[0] === '_') { continue; } //required for _parents
            $res[$k] = $v;
        }
        return $res;
    }

    public function getCustomVars($flat = false)
    {
        $res = array();
        foreach ($this->props as $k => $v) {
            if ($k[0] !== '_') { continue; }
            if ($flat) {
                $res[] = $k . ' ' . utf8_encode($v); // TODO: Get rid of this utf8-thing
            } else {
                $res[$k] = $v;
            }
        }
        return $res;
    }

    public function hasCustomVars()
    {
        return count($this->getCustomVars()) > 0;
    }

    protected function configPairString($k, $v)
    {
        $max = 24;
        $space = ' ';
        if (strlen($k) < $max) {
            $space .= str_repeat(' ', ($max - strlen($k)));
        }
        return '    ' . $k . $space . $v . "\n";
    }

    public function dump($return = false)
    {
        $str = 'define ' . $this->getDefinitionType() . "{\n";
        $key = $this->isTemplate() ? 'name' : $this->key;
        $str .= $this->configPairString($key, $this->$key);
        foreach ($this->getAttributes() as $k => $v) {
            $str .= $this->configPairString($k, $v);
        }
        if ($this->isTemplate()) {
            $str .= $this->configPairString('register', '0');
        }
        $str .= "}\n\n";
        if ($return) {
            return $str;
        } else {
            echo $str;
        }
    }

    public function isTemplate()
    {
        return $this->is_template;
    }

    public function __toString()
    {
        if ($this->isTemplate()) {
            if ($this->name) {
               return $this->name;
            } elseif ($this->{$this->key}) {
               return $this->{$this->key};
            } else {
               echo "FOOBAR: no key found. but template.";
               var_dump($this);
               return null; // Will fail badly
            }
        } else {
            if ($this->{$this->key}) {
                return $this->{$this->key};
            } elseif ($this->name) {
                return $this->name;
            } else {
                echo "no template: no key/name found.";
                var_dump($this);
                return null; // Will fail badly
            }
        }
    }

    public static function factory($type, $props)
    {
        $class = __NAMESPACE__ . '\\Icinga' . ucfirst($type);
        $def = new $class($props);
        return $def;
    }

    public function __set($key, $val)
    {
        switch($key) {
            case 'register':
                if ((bool) $val) {
                    $this->is_template = false;
                } else {
                    $this->is_template = true;
                }
                break;
            default:
                $this->props->$key = $val;
        }
    }

    public function __get($key)
    {
        if (isset($this->props->$key)) {
            return $this->props->$key;
        } else {
            foreach ($this->_parents as $parent) {
                if ($parent->$key !== null) {
                    return $parent->$key;
                }
            }
            return null;
        }
    }

    public function addParent(IcingaTemplate $parent)
    {
        $this->_parents[] = $parent;
        return $this;
    }

    public function getParents()
    {
        return $this->_parents;
    }

    public function validate()
    {
        if (! $this->isTemplate()) {
            $this->assertProperty($this->key);
        }
    }

    protected function assertProperty($key)
    {
        if (! isset($this->props->$key) && ! property_exists($this->props, $key))
        {
            throw new IcingaDefinitionException(sprintf(
                'Icinga definition [%s] is missing property %s',
                $this->getDefinitionType(),
                $key
            ));
        }
    }
}
