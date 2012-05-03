<?php

namespace Guzzle\Common;

/**
 * Key value pair collection object
 */
class Collection implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /**
     * @var array Data associated with the object.
     */
    protected $data;

    /**
     * Constructor
     *
     * @param array $data Associative array of data to set
     */
    public function __construct(array $data = null)
    {
        $this->data = $data ?: array();
    }

    /**
     * Convert the object to a string
     *
     * @return string
     */
    public function __toString()
    {
        return __CLASS__ . '@' . spl_object_hash($this);
    }

    /**
     * Add a value to a key.  If a key of the same name has
     * already been added, the key value will be converted into an array
     * and the new value will be pushed to the end of the array.
     *
     * @param string $key Key to add
     * @param mixed $value Value to add to the key
     *
     * @return Collection Returns a reference to the object.
     */
    public function add($key, $value)
    {
        if (!array_key_exists($key, $this->data)) {
            $this->data[$key] = $value;
        } else {
            if (!is_array($this->data[$key])) {
                $this->data[$key] = array($this->data[$key], $value);
            } else {
                $this->data[$key][] = $value;
            }
        }

        return $this;
    }

    /**
     * Removes all key value pairs
     *
     * @return Collection
     */
    public function clear()
    {
        $this->data = array();

        return $this;
    }

    /**
     * Return the number of keys
     *
     * @return integer
     */
    public function count()
    {
        return count($this->data);
    }

    /**
     * Iterates over each key value pair in the collection passing them to the
     * Closure. If the  Closure function returns true, the current value from
     * input is returned into the result Collection.  The Closure must accept
     * three parameters: (string) $key, (string) $value and
     * return Boolean TRUE or FALSE for each value.
     *
     * @param Closure $closure Closure evaluation function
     * @param bool $static Set to TRUE to use the same class as the return
     *      rather than returning a Collection object
     *
     * @return Collection
     */
    public function filter(\Closure $closure, $static = true)
    {
        $collection = ($static) ? new static() : new self();
        foreach ($this->data as $key => $value) {
            if ($closure($key, $value)) {
                $collection->add($key, $value);
            }
        }

        return $collection;
    }

    /**
     * Get an iterator object
     *
     * @return array
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->data);
    }

    /**
     * Get a specific key value.
     *
     * @param string $key Key to retrieve.
     * @param mixed $default (optional) If the key is not found, set this
     *      value to specify a default
     *
     * @return mixed|null Value of the key or NULL
     */
    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        return $default;
    }

    /**
     * Get all or a subset of matching key value pairs
     *
     * @param array $keys (optional) Pass a single key or an array
     *      of keys to retrieve only a particular subset of kvp.
     *
     * @return array Returns an array of all matching key value pairs
     */
    public function getAll(array $keys = null)
    {
        if (!$keys) {
            return $this->data;
        }

        $matches = array();
        $allKeys = $this->getKeys();
        foreach ($keys as $key) {
            if (in_array($key, $allKeys)) {
                $matches[$key] = $this->data[$key];
            }
        }

        return $matches;
    }

    /**
     * Get all keys in the collection
     *
     * @return array
     */
    public function getKeys()
    {
        return array_keys($this->data);
    }

    /**
     * Returns whether or not the specified key is present.
     *
     * @param string $key The key for which to check the existence.
     *
     * @return bool
     */
    public function hasKey($key)
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Checks if any keys contains a certain value
     *
     * @param string $value Value to search for
     *
     * @return mixed Returns the key if the value was found FALSE if
     *      the value was not found.
     */
    public function hasValue($value)
    {
        return array_search($value, $this->data);
    }

    /**
     * Returns a Collection containing all the elements of the collection after
     * applying the callback function to each one. The Closure should accept
     * three parameters: (string) $key, (string) $value, (array) $context and
     * return a modified value
     *
     * @param Closure $closure Closure to apply
     * @param array $context (optional) Context to pass to the closure
     * @param bool $static Set to TRUE to use the same class as the return
     *      rather than returning a Collection object
     *
     * @return Collection
     */
    public function map(\Closure $closure, array $context = array(), $static = true)
    {
        $collection = $static ? new static() : new self();
        foreach ($this as $key => $value) {
            $collection->add($key, $closure($key, $value, $context));
        }

        return $collection;
    }

    /**
     * Add and merge in a Collection or array of key value pair data.
     *
     * Invalid $data arguments will silently fail.
     *
     * @param Collection|array $data Associative array of key value pair data
     *
     * @return Collection Returns a reference to the object.
     */
    public function merge($data)
    {
        if ($data instanceof self) {
            $data = $data->getAll();
        } else if (!is_array($data)) {
            return $this;
        }

        if (!empty($data)) {
            if (empty($this->data)) {
                $this->data = $data;
            } else {
                foreach ($data as $key => $value) {
                    $this->add($key, $value);
                }
            }
        }

        return $this;
    }

    /**
     * ArrayAccess implementation of offsetExists()
     *
     * @param string $offset Array key
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return $this->hasKey($offset) !== false;
    }

    /**
     * ArrayAccess implementation of offsetGet()
     *
     * @param string $offset Array key
     *
     * @return null|mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess implementation of offsetGet()
     *
     * @param string $offset Array key
     * @param mixed $value Value to set
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * ArrayAccess implementation of offsetUnset()
     *
     * @param string $offset Array key
     */
    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    /**
     * Remove a specific key value pair
     *
     * @param string $key A key to remove
     *
     * @return Collection
     */
    public function remove($key)
    {
        unset($this->data[$key]);

        return $this;
    }

    /**
     * Replace the data of the object with the value of an array
     *
     * @param array $data Associative array of data
     *
     * @return Collection Returns a reference to the object
     */
    public function replace(array $data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Set a key value pair
     *
     * @param string $key Key to set
     * @param mixed $value Value to set
     *
     * @return Collection Returns a reference to the object
     */
    public function set($key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }
}
