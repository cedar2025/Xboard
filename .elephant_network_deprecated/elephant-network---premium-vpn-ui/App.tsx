
import React, { useState, useEffect } from 'react';
import { AppView, UserData } from './types';
import Login from './components/Login';
import Register from './components/Register';
import ForgotPassword from './components/ForgotPassword';
import Dashboard from './components/Dashboard';
import Profile from './components/Profile';
import Nodes from './components/Nodes';
import Shop from './components/Shop';
import Navigation from './components/Navigation';

const App: React.FC = () => {
  const [currentView, setCurrentView] = useState<AppView>(AppView.LOGIN);
  const [isLoggedIn, setIsLoggedIn] = useState(false);
  const [theme, setTheme] = useState<'light' | 'dark'>('light');

  const toggleTheme = () => {
    setTheme(prev => prev === 'light' ? 'dark' : 'light');
  };

  const mockUser: UserData = {
    email: 'ccy@gmail.com',
    expiryDate: '2026-01-22',
    balance: 0.00,
    remainingDays: 5,
    totalData: 300 * 1024 * 1024 * 1024, // 300GB in bytes
    usedData: 0,
    isPremium: true
  };

  const handleLogin = () => {
    setIsLoggedIn(true);
    setCurrentView(AppView.DASHBOARD);
  };

  const handleLogout = () => {
    setIsLoggedIn(false);
    setCurrentView(AppView.LOGIN);
  };

  const renderView = () => {
    switch (currentView) {
      case AppView.LOGIN:
        return (
          <Login 
            onLogin={handleLogin} 
            theme={theme} 
            onGoToRegister={() => setCurrentView(AppView.REGISTER)} 
            onGoToForgot={() => setCurrentView(AppView.FORGOT_PASSWORD)}
          />
        );
      case AppView.REGISTER:
        return <Register onBack={() => setCurrentView(AppView.LOGIN)} theme="light" />;
      case AppView.FORGOT_PASSWORD:
        return <ForgotPassword onBack={() => setCurrentView(AppView.LOGIN)} theme="light" />;
      case AppView.DASHBOARD:
        return <Dashboard user={mockUser} onOpenNodes={() => setCurrentView(AppView.NODES)} onOpenShop={() => setCurrentView(AppView.SHOP)} theme={theme} />;
      case AppView.PROFILE:
        return <Profile user={mockUser} onLogout={handleLogout} onBack={() => setCurrentView(AppView.DASHBOARD)} theme={theme} onToggleTheme={toggleTheme} />;
      case AppView.NODES:
        return <Nodes onBack={() => setCurrentView(AppView.DASHBOARD)} theme={theme} />;
      case AppView.SHOP:
        return <Shop onBack={() => setCurrentView(AppView.DASHBOARD)} theme={theme} />;
      default:
        return <Dashboard user={mockUser} onOpenNodes={() => setCurrentView(AppView.NODES)} onOpenShop={() => setCurrentView(AppView.SHOP)} theme={theme} />;
    }
  };

  const isAuthView = [AppView.LOGIN, AppView.REGISTER, AppView.FORGOT_PASSWORD].includes(currentView);

  return (
    <div className={`min-h-screen ${theme === 'dark' && !isAuthView ? 'bg-[#0f172a]' : 'bg-slate-50'} flex flex-col max-w-md mx-auto relative shadow-2xl overflow-hidden transition-colors duration-500`}>
      <main className="flex-1 overflow-y-auto pb-24">
        {renderView()}
      </main>
      
      {!isAuthView && isLoggedIn && (
        <Navigation 
          activeView={currentView} 
          setView={setCurrentView} 
          theme={theme}
        />
      )}
    </div>
  );
};

export default App;
