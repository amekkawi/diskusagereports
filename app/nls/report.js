define({
	"root": {
		"footer": "Report generated using <%= link %>.",
		"footer_long": "Report generated on <%- created %> using <%= link %>.",
		"title": "Disk Usage Report",
		"title_long": "Disk Usage Report for: <b><%- name %></b>",
		"total_size": "Total Size: <%- total %> (<%- direct %> in this directory alone)",
		"total_files": "Total Files: <%- total %> (<%- direct %> in this directory alone)",

		"tab_dirs": "Contents",
		"tab_files": "File List",
		"tab_modified": "Last Modified",
		"tab_sizes": "File Sizes",
		"tab_ext": "File Types",
		"tab_top": "Top 100",

		message_loading: "Loading...",
		message_settings: "The report could not be loaded. Error: <%= status %>",
		message_settings_200: "The settings file for the report is corrupted or is not JSON.",
		message_settings_304: "The settings file for the report is corrupted or is not JSON.",
		message_settings_404: "The report was not found.<br>Verify that the web address is correct and refresh to try again.",
		message_settings_invalid: "The settings file for the report has invalid values."
	}
});