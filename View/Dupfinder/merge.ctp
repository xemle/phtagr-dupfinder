<h1>Merge Results</h1>

<p><?php echo $this->request->data['copies']; ?> copies were merged to <?php echo $this->request->data['master']; ?> master media.</p>

<p>Search for other duplicates <?php echo $this->Html->link('again', array('action' => 'find')); ?>.</p>
