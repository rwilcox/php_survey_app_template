var unique_code = new LiveValidation( "unique_code", {onlyOnSubmit: true} );
unique_code.add(Validate.Format, {pattern: /^AE/i})

var email_address = new LiveValidation( "email_address", {onlyOnSubmit: true} );
email_address.add( Validate.Presence);

var email_address_verify = new LiveValidation("email_address_verify", {onlyOnSubmit: true});
email_address_verify.add( Validate.Confirmation, {match: "email_address"} );


var i_accept = new LiveValidation("i_accept", {onlyOnSubmit: true});
i_accept.add( Validate.Acceptance );