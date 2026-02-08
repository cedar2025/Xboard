
import React from 'react';
import { ChevronLeft, Check, Flame } from 'lucide-react';

interface Props {
  onBack: () => void;
  theme: 'light' | 'dark';
}

const Shop: React.FC<Props> = ({ onBack, theme }) => {
  const isDark = theme === 'dark';
  const plans = [
    {
      name: '基础套餐 (月付)',
      price: '35.0',
      period: '月',
      isHot: true,
      features: [
        '月流量 100-200GB (倍率 1x)',
        '标准节点访问 (中转线路为主)',
        '同时在线设备 3-5 个',
        '支持主流协议及客户端'
      ]
    },
    {
      name: '标准套餐 (月付)',
      price: '50.0',
      period: '月',
      isHot: true,
      features: [
        '月流量 300-600GB (含专线)',
        '全节点解锁 (Netflix, ChatGPT)',
        '在线设备 5-10 个, UDP 转发',
        '高峰期极速保障'
      ]
    },
    {
      name: '高端VIP套餐',
      price: '99.0',
      period: '月',
      isHot: true,
      features: [
        '月流量 1000GB+ (全专线)',
        '全球流媒体 4K/8K 观看',
        '尊享最高优先级技术支持',
        '支持全平台最高速体验'
      ]
    }
  ];

  return (
    <div className={`p-6 pb-12 transition-colors duration-500`}>
      <header className="relative flex items-center justify-center mb-8 h-10">
        <button 
          onClick={onBack} 
          className={`absolute left-0 p-2 -ml-2 rounded-full transition-colors ${isDark ? 'hover:bg-slate-800' : 'hover:bg-slate-100'}`}
        >
          <ChevronLeft size={24} className={isDark ? 'text-white' : 'text-slate-900'} />
        </button>
        <h1 className={`text-xl font-bold tracking-tight transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>购买套餐</h1>
      </header>

      <div className="space-y-6">
        {plans.map((plan, index) => (
          <div 
            key={index} 
            className={`rounded-3xl p-6 shadow-sm border transition-all relative overflow-hidden ${
              isDark 
              ? 'bg-slate-900 border-slate-800 hover:border-emerald-900' 
              : 'bg-white border-slate-100 hover:border-emerald-200'
            }`}
          >
            {plan.isHot && (
              <div className="absolute top-0 right-0">
                <div className={`px-3 py-1 text-[10px] font-black uppercase tracking-widest rounded-bl-xl flex items-center space-x-1 transition-colors ${
                  isDark ? 'bg-emerald-950/50 text-emerald-400' : 'bg-emerald-50 text-emerald-600'
                }`}>
                  <Flame size={10} className={isDark ? 'fill-emerald-400' : 'fill-emerald-500'} />
                  <span>Hot</span>
                </div>
              </div>
            )}

            <div className="mb-6">
              <h2 className={`text-lg font-bold mb-4 transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>{plan.name}</h2>
              <ul className="space-y-3">
                {plan.features.map((feature, fIndex) => (
                  <li key={fIndex} className="flex items-start space-x-3">
                    <div className={`mt-1 flex-shrink-0 w-4 h-4 rounded-full flex items-center justify-center transition-colors ${
                      isDark ? 'bg-slate-800' : 'bg-emerald-50'
                    }`}>
                      <Check size={10} className={`${isDark ? 'text-emerald-400' : 'text-emerald-500'} stroke-[3px]`} />
                    </div>
                    <span className={`text-sm leading-tight transition-colors ${isDark ? 'text-slate-400' : 'text-slate-500'}`}>
                      {feature}
                    </span>
                  </li>
                ))}
              </ul>
            </div>

            <div className="flex items-baseline space-x-1 mb-6">
              <span className={`${isDark ? 'text-emerald-400' : 'text-emerald-500'} font-bold text-lg`}>¥</span>
              <span className={`text-4xl font-black transition-colors ${isDark ? 'text-white' : 'text-slate-900'}`}>{plan.price}</span>
              <span className={`text-sm font-medium transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>/ {plan.period}</span>
            </div>

            <button className={`w-full font-bold py-4 rounded-2xl shadow-lg active:scale-[0.98] transition-all ${
              isDark 
              ? 'bg-emerald-600 hover:bg-emerald-500 text-white shadow-emerald-900/20' 
              : 'bg-emerald-500 hover:bg-emerald-600 text-white shadow-emerald-50'
            }`}>
              立即订阅
            </button>
          </div>
        ))}
      </div>

      <div className="mt-8 mb-4 text-center">
        <p className={`text-xs transition-colors ${isDark ? 'text-slate-600' : 'text-slate-400'}`}>
          订阅即表示您同意我们的 <button className="underline">服务条款</button>
        </p>
      </div>
    </div>
  );
};

export default Shop;
