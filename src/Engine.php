<?php

namespace joshmoody\Validation;

use \Exception;
use \InvalidArgumentException;

class Engine
{
	public $validator;
	public $fields;
	public $errors;
	public $messages;
	public $data;

	public function __construct($validator = null)
	{
		if (empty($validator)) {
			// Use the default validation engine.
			$this->validator = new Validator();
		} else {
			$this->validator = $validator;
		}

		// Initialize default values for class variables.
		$this->reset();
		
		$this->loadDefaultMessages();
	}

	public function parseRule($input)
	{
		$return = [];

		# Split the string on pipe to get individual rules.
		$rules = explode('|', $input);

		foreach ($rules as $r) {

			$rule_name = $r;
			$rule_params = [];

			// For each rule in the list, see if it has any parameters. Example: minlength[5].
			if (preg_match('/\[(.*?)\]/', $r, $matches)) {

				// This one has parameters. Split out the rule name from it's parameters.
				$rule_name = substr($r, 0, strpos($r, '['));

				// There may be more than one parameters.
				$rule_params = explode(',', $matches[1]);
			} elseif (preg_match('/\{(.*?)\}/', $r, $matches)) {
				// This one has an array parameter. Split out the rule name from it's parameters.
				$rule_name = substr($r, 0, strpos($r, '{'));

				// There may be more than one parameter.
				$rule_params = array(explode(',', $matches[1]));
			}

			$return[$rule_name] = $rule_params;
		}

		return $return;
	}

	public function addRuleSet($ruleset)
	{
		if (!is_subclass_of($ruleset, 'joshmoody\Validation\Ruleset')) {
			throw new InvalidArgumentException('ruleset must be a subclass of joshmoody\Validation\Ruleset');
		}
		
		foreach ($ruleset->rules as $rule) {
			$this->addRule($rule['field'], $rule['label'], $rule['rules']);
		}
	}
	
	public function addRule($field = null, $label = null, $rules = null)
	{
		if (empty($field) || empty($label) || empty($rules)) {
			throw new InvalidArgumentException('Field, Label, and Rules are required.');
		}

		// Add this field to our list of fields (unless it already exists).
		if (!array_key_exists($field, $this->fields)) {
			$this->fields[$field] = ['label' => $label];
		}

		if ($rules instanceof \Closure) {
			$closure = $rules;
			$this->fields[$field]['rules'][] = $closure;
		} else {
			$rules = $this->parseRule($rules);
	
			foreach ($rules as $rule => $params) {
	
				if (count($params) > 0) {
					foreach ($params as $param) {
						$this->fields[$field]['rules'][$rule]['params'][] = $param;
					}
				} else {
					$this->fields[$field]['rules'][$rule]['params'] = [];
				}
			}
		}
	}

	public function validate($data = [])
	{
		$this->data = $data;
		
		foreach ($this->fields as $id => $attributes) {

			$input = array_key_exists($id, $data) ? $data[$id] : null;
			$label = $attributes['label'];

			foreach ($attributes['rules'] as $method => $opts) {

				if ($opts instanceof \Closure) {
					list($success, $message) = $opts($this->data, $id, $label);
					
					if (!$success) {
						$this->errors[$id][] = $message;
					}
				} else {
					$args = [];
					$args[] = $input;
	
					foreach ($opts['params'] as $param) {
						$args[] = $param;
					}
	
					$success = call_user_func_array([$this->validator, $method], $args);

					if (!$success) {
						$this->errors[$id][] = $this->getMessage($label, $method, $opts['params']);
					}
				}
			}
		}

		return $this->hasErrors() ? false : true;
	}

	public function getErrorSummary()
	{
		$summary = [];

		foreach ($this->errors as $field => $messages) {
			foreach ($messages as $message) {
				$summary[] = $message;
			}
		}
		return $summary;
	}

	public function getErrorSummaryFormatted($outerwrapper = ['<div class="alert alert-danger"><ul>','</ul></div>'], $innerwrapper = ['<li>','</li>'])
	{
		$summary = $this->getErrorSummary();
		
		if (count($summary) > 0) {
			$formatted = [];
			
			$formatted[] = $outerwrapper[0];
			
			foreach ($summary as $s) {
				$formatted[] = $innerwrapper[0];
				$formatted[] = $s;
				$formatted[] = $innerwrapper[1];
			}

			$formatted[] = $outerwrapper[1];
			
			return join($formatted, PHP_EOL);
			
		} else {
			return null;
		}
	}
	
	public function getErrorDetail()
	{
		$detail = [];
		
		foreach ($this->errors as $field => $messages) {
			$detail[] = ['field' => $field, 'messages' => $messages];
		}
		
		return $detail;
	}
	
	public function getErrorFields()
	{
		$fields = [];

		foreach ($this->errors as $field => $messages) {
			$fields[] = $field;
		}
		return $fields;

	}

	public function getRequiredFields()
	{
		$required = [];

		foreach ($this->fields as $field => $attributes) {
			if (array_key_exists('required', $attributes['rules'])) {
				$required[] = $field;
			}
		}

		return $required;
	}

	public function loadDefaultMessages()
	{
		$messages = [];
		$messages['required'] = '{name} is a required field.';
		$messages['date'] = '{name} must be a valid date.';
		$messages['minlength'] = '{name} must be at least %s characters long.';
		$messages['maxlength'] = '{name} cannot be longer than %d characters.';
		$messages['exactlength'] = '{name} must be exactly %d characters.';
		$messages['greaterthan'] = '{name} must be greater than %d.';
		$messages['lessthan'] = '{name} must be less than %d.';
		$messages['alpha'] = '{name} must only contain the letters A-Z.';
		$messages['alphanumeric'] = '{name} must only contain the letters A-Z and numbers 0-9.';
		$messages['integer'] = '{name} must be a whole number with no decimals';
		$messages['float'] = '{name} must be a number.';
		$messages['numeric'] = '{name} must be numeric.';
		$messages['email'] = '{name} must be a valid email address.';
		$messages['url'] = '{name} must be a valid url.';
		$messages['phone'] = '{name} must be a valid phone number.';
		$messages['zipcode'] = '{name} must be a valid zip code.';
		$messages['startswith'] = '{name} must start with %s.';
		$messages['endswith'] = '{name} must end with %s.';
		$messages['contains'] = '{name} must contain %s.';
		$messages['regex'] = '{name} is not in the correct format.';

		foreach ($messages as $key => $value) {
			$this->messages[$key] = $value;
		}
	}

	public function addCustomMessage($validator, $message)
	{
		$this->messages[$validator]	= $message;
	}
	
	public function getMessage($field, $rule, $params = [])
	{

		if (!array_key_exists($rule, $this->messages)) {
			$format = '{name} is invalid.';
		} else {
			$format = $this->messages[$rule];
		}

		$string = str_replace('{name}', $field, $format);
		return vsprintf($string, $params);
	}

	/**
	 * Are there any validation errors?
	 */
	public function hasErrors()
	{
		return (count($this->errors) > 0);
	}

	/**
	 * Retrieve a data element that was validated.
	 *
	 * Useful if you want to pass the validator into a view to prefill inputs.
	 * 
	 * @access public
	 * @param mixed $field (default: null) The id of the element to return. If not provided, it will return the entire data array.
	 * @return mixed
	 */
	public function getData($field = null)
	{
		if (empty($field)) {
			// No element specified. Return all of the data that was validated.
			return $this->data;
		}
		
		if (!empty($this->data) && array_key_exists($field, $this->data)) {
			return $this->data[$field];
		}
		
		return null;
	}
	
	public function reset()
	{
		$this->fields = [];
		$this->errors = [];
		$this->messages = [];
		$this->data = [];
	}
}
