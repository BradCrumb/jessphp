var jess = {
	// Module definitions.
	__definitions: {},
	// Module instances.
	__instances: {},
	//Define a module
	define: function(id, arg) {
		// accept definition
		this.__definitions[id] = {
			id: id,
			fn: typeof(arg) == 'function' ? arg : function () {
				return arg;
			}
		};
	},
	//Require a module
	require: function(id) {
		if (this.__instances.id) {
			return this.__instances.id;
		}

		var def = this.__definitions[id];

		this.__instances.id = def;

		return def.fn();
	}
};

<modules>