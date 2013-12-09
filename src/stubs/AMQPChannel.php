<?php
/**
 * stub class representing AMQPChannel from pecl-amqp
 */
class AMQPChannel
{
	/**
	 * Commit a pending transaction.
	 *
	 * @throws AMQPChannelException    If no transaction was started prior to
	 *                                 calling this method.
	 * @throws AMQPConnectionException If the connection to the broker was lost.
	 *
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function commitTransaction()
	{
	}

	/**
	 * Create an instance of an AMQPChannel object.
	 *
	 * @param AMQPConnection $amqp_connection An instance of AMQPConnection
	 *                                        with an active connection to a
	 *                                        broker.
	 *
	 * @throws AMQPConnectionException        If the connection to the broker
	 *                                        was lost.
	 */
	public function __construct(AMQPConnection $amqp_connection)
	{
	}

	/**
	 * Check the channel connection.
	 *
	 * @return bool Indicates whether the channel is connected.
	 */
	public function isConnected()
	{
	}

	/**
	 * Return internal channel ID
	 *
	 * @return integer
	 */
	public function getChannelId()
	{
	}

	/**
	 * Set the Quality Of Service settings for the given channel.
	 *
	 * Specify the amount of data to prefetch in terms of window size (octets)
	 * or number of messages from a queue during a AMQPQueue::consume() or
	 * AMQPQueue::get() method call. The client will prefetch data up to size
	 * octets or count messages from the server, whichever limit is hit first.
	 * Setting either value to 0 will instruct the client to ignore that
	 * particular setting. A call to AMQPChannel::qos() will overwrite any
	 * values set by calling AMQPChannel::setPrefetchSize() and
	 * AMQPChannel::setPrefetchCount(). If the call to either
	 * AMQPQueue::consume() or AMQPQueue::get() is done with the AMQP_AUTOACK
	 * flag set, the client will not do any prefetching of data, regardless of
	 * the QOS settings.
	 *
	 * @param integer $size  The window size, in octets, to prefetch.
	 * @param integer $count The number of messages to prefetch.
	 *
	 * @throws AMQPConnectionException If the connection to the broker was lost.
	 *
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function qos($size, $count)
	{
	}

	/**
	 * Rollback a transaction.
	 *
	 * Rollback an existing transaction. AMQPChannel::startTransaction() must
	 * be called prior to this.
	 *
	 * @throws AMQPChannelException    If no transaction was started prior to
	 *                                 calling this method.
	 * @throws AMQPConnectionException If the connection to the broker was lost.
	 *
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function rollbackTransaction()
	{
	}

	/**
	 * Set the number of messages to prefetch from the broker.
	 *
	 * Set the number of messages to prefetch from the broker during a call to
	 * AMQPQueue::consume() or AMQPQueue::get(). Any call to this method will
	 * automatically set the prefetch window size to 0, meaning that the
	 * prefetch window size setting will be ignored.
	 *
	 * @param integer $count The number of messages to prefetch.
	 *
	 * @throws AMQPConnectionException If the connection to the broker was lost.
	 *
	 * @return boolean TRUE on success or FALSE on failure.
	 */
	public function setPrefetchCount($count)
	{
	}

	/**
	 * Get the number of messages to prefetch from the broker.
	 *
	 * @return integer
	 */
	public function getPrefetchCount()
	{
	}

	/**
	 * Set the window size to prefetch from the broker.
	 *
	 * Set the prefetch window size, in octets, during a call to
	 * AMQPQueue::consume() or AMQPQueue::get(). Any call to this method will
	 * automatically set the prefetch message count to 0, meaning that the
	 * prefetch message count setting will be ignored. If the call to either
	 * AMQPQueue::consume() or AMQPQueue::get() is done with the AMQP_AUTOACK
	 * flag set, this setting will be ignored.
	 *
	 * @param integer $size The window size, in octets, to prefetch.
	 *
	 * @throws AMQPConnectionException If the connection to the broker was lost.
	 *
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function setPrefetchSize($size)
	{
	}

	/**
	 * Get the window size to prefetch from the broker.
	 *
	 * @return integer
	 */
	public function getPrefetchSize()
	{
	}

	/**
	 * Start a transaction.
	 *
	 * This method must be called on the given channel prior to calling
	 * AMQPChannel::commitTransaction() or AMQPChannel::rollbackTransaction().
	 *
	 * @throws AMQPConnectionException If the connection to the broker was lost.
	 *
	 * @return bool TRUE on success or FALSE on failure.
	 */
	public function startTransaction()
	{
	}
}

