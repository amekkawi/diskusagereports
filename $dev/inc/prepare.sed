# Comment out all <script> tags.
s#^<script type="text/javascript" src="js/.+"></script>$#<!-- & -->#

# Uncomment the packed.js <script> tag.
s#^<!-- (<script type="text/javascript" src="js/packed\.js"></script>) -->$#\1#

# Use jquery.min.js instead.
s#"js/external/jquery.js"#"js/external/jquery.min.js"#
