<h1>Merge Results</h1>

<p><?php echo $this->data['copies']; ?> copies were merged to <?php echo $this->data['master']; ?> master media.</p>

<p>Search for other duplicates <?php echo $html->link('again', array('action' => 'find')); ?>.</p>
