module FormsHelper
  def many_checkboxes(options_list, delimiter=" ", horz=false)
    li_style = ""
    if horz
      li_style="style='display: inline; list-style-type: none; margin-right: 2em'"
    end
    
    res = options_list.collect {|curr| "<li #{li_style}>" + input_item(curr.merge(:input_style => "")) + "</li>"}
  	"<ul>" + res.join(" ") + "</ul>"
  end


  def input_item(options)

    label_text = options[:label]
    input_type = options[:type]
    input_name = options[:name]
    input_id = options[:id] || options[:name]
    input_class_override = options[:input_class]
    use_tables = options[:use_tables]
    input_other_attibutes = options[:attributes]
    default = options[:php_default]

    input_special_style = ""
    
    if use_tables
      input_special_style = options[:input_style] || ""
    else
      input_special_style = options[:input_style] || case input_type
        when "text"
          "margin-left: 5em"
        when "checkbox"
          "margin-right: 5em"
        else 
          ""
        end
    end

    input_class = input_type
    if input_class_override
      input_class = input_type + " " + input_class_override
    end

    # now save these options to a file, so we can generate PHP on (some) other end
    already_data = {}
    output_path = ::Webby.site.content_dir + "/../tmp/input_options.yml"
    if File.exists? output_path
      already_data = YAML.load_file(output_path) || {}
    end

    File.open(output_path, 'w') do |file|
      already_data[input_name] = options
      file << "\n" << already_data.to_yaml
    end

    label_styles = ""
    if options[:label_style]
      label_style = "style='#{options[:label_style]}'"
    end
    unless use_tables
      return "<label class='title' for='#{input_id}' #{label_style}>#{label_text}</label><input type='#{input_type}' class='#{input_class}' name='#{input_name}' value='' id='#{input_id}' style='#{input_special_style}' #{input_other_attibutes}>"
    else
      return "<tr><td><label class='title' for='#{input_id}'>#{label_text}</label></td><td><input type='#{input_type}' class='#{input_class}' name='#{input_name}' value='' id='#{input_id}' style='#{input_special_style}' #{input_other_attibutes}></td></tr>"
    end
  end


  def register_text_area(options)
    label_text = options[:label]
    input_type = options[:type]
    input_name = options[:name]
    input_id = options[:id] || options[:name]
    default = options[:php_default]

    # now save these options to a file, so we can generate PHP on (some) other end
    already_data = {}
    output_path = ::Webby.site.content_dir + "/../tmp/input_options.yml"
    if File.exists? output_path
      already_data = YAML.load_file(output_path) || {}
    end

    File.open(output_path, 'w') do |file|
      already_data[input_name] = options
      file << "\n" << already_data.to_yaml
    end
  end

end

Webby::Helpers.register(FormsHelper)
