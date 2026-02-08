package cn.moncn.sing_box_windows.vpn

import android.net.DnsResolver
import android.os.CancellationSignal
import io.nekohasekai.libbox.ExchangeContext
import io.nekohasekai.libbox.Func
import io.nekohasekai.libbox.LocalDNSTransport
import java.io.IOException
import java.net.InetAddress
import java.util.concurrent.CountDownLatch
import java.util.concurrent.Executor
import java.util.concurrent.TimeUnit

object LocalResolver : LocalDNSTransport {
    private val resolver = DnsResolver.getInstance()
    private val executor = Executor { it.run() }

    override fun raw(): Boolean = false

    override fun lookup(context: ExchangeContext, network: String, domain: String) {
        val type = when (network.lowercase()) {
            "ip4" -> DnsResolver.TYPE_A
            "ip6" -> DnsResolver.TYPE_AAAA
            else -> throw IllegalArgumentException("unsupported dns network: $network")
        }

        val latch = CountDownLatch(1)
        val cancelSignal = CancellationSignal()
        context.onCancel(object : Func {
            override fun invoke() {
                cancelSignal.cancel()
            }
        })

        var error: Exception? = null
        var answer: List<InetAddress>? = null
        val normalizedDomain = domain.trimEnd('.')

        resolver.query(
            null,
            normalizedDomain,
            type,
            executor,
            cancelSignal,
            object : DnsResolver.Callback<List<InetAddress>> {
                override fun onAnswer(addresses: List<InetAddress>, rcode: Int) {
                    answer = addresses
                    latch.countDown()
                }

                override fun onError(e: DnsResolver.DnsException) {
                    error = e
                    latch.countDown()
                }
            }
        )

        if (!latch.await(5, TimeUnit.SECONDS)) {
            throw IOException("dns lookup timeout")
        }
        error?.let { throw it }

        val result = answer?.joinToString("\n") { it.hostAddress } ?: ""
        context.success(result)
    }

    override fun exchange(context: ExchangeContext, message: ByteArray) {
        throw UnsupportedOperationException("raw dns not supported")
    }
}
